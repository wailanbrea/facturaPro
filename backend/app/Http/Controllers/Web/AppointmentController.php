<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Services\ActivityLogService;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly FcmService $fcm,
    ) {}

    public function index(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $appointments = Appointment::query()
            ->with('creator', 'client')
            ->whereBetween('start_at', [$start->startOfWeek(), $end->endOfWeek()])
            ->orderBy('start_at')
            ->get();

        return view('appointments.index', compact('appointments', 'year', 'month', 'start', 'end'));
    }

    public function create(Request $request): View
    {
        $clients = Client::query()->where('is_active', true)->orderBy('name')->get();
        $defaultDate = $request->get('date', now()->toDateString());

        return view('appointments.create', compact('clients', 'defaultDate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'service_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:100'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);

        $this->checkConflicts($data['start_at'], $data['end_at']);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $appointment = Appointment::query()->create([
            ...$data,
            'client_name' => $client?->name,
            'created_by' => auth()->id(),
            'status' => $data['status'] ?? Appointment::STATUS_PENDING,
        ]);

        $this->activityLog->record('appointment.created', $appointment, ['title' => $appointment->title], auth()->user(), $request);

        $this->fcm->notifyUsers(
            $this->calendarUserIds(),
            'Nueva cita agendada',
            $appointment->title.' — '.$appointment->start_at->format('d/m H:i'),
            ['appointment_id' => (string) $appointment->id, 'action' => 'created'],
        );

        return redirect()->route('web.appointments.show', $appointment)
            ->with('status', 'Cita creada correctamente.');
    }

    public function show(Appointment $appointment): View
    {
        $logs = \App\Models\ActivityLog::query()
            ->where('subject_type', Appointment::class)
            ->where('subject_id', $appointment->id)
            ->with('user')
            ->latest()
            ->get();

        return view('appointments.show', compact('appointment', 'logs'));
    }

    public function edit(Appointment $appointment): RedirectResponse|View
    {
        $this->authorize('update', $appointment);

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get();

        return view('appointments.edit', compact('appointment', 'clients'));
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'service_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:100'],
            'status' => ['required', Rule::in(Appointment::STATUSES)],
        ]);

        $this->checkConflicts($data['start_at'], $data['end_at'], $appointment->id);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $oldStatus = $appointment->status;

        $appointment->update([
            ...$data,
            'client_name' => $client?->name ?? $appointment->client_name,
        ]);

        $this->activityLog->record(
            'appointment.updated',
            $appointment,
            ['old_status' => $oldStatus, 'new_status' => $appointment->status],
            auth()->user(),
            $request,
        );

        if ($oldStatus !== $appointment->status) {
            $this->fcm->notifyUsers(
                $this->calendarUserIds(),
                'Cita actualizada',
                $appointment->title.' — '.Appointment::STATUS_LABELS[$appointment->status],
                ['appointment_id' => (string) $appointment->id, 'action' => 'status_changed'],
            );
        }

        return redirect()->route('web.appointments.show', $appointment)
            ->with('status', 'Cita actualizada correctamente.');
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)],
        ]);

        $oldStatus = $appointment->status;
        $appointment->update(['status' => $data['status']]);

        $this->activityLog->record(
            'appointment.status_changed',
            $appointment,
            ['old_status' => $oldStatus, 'new_status' => $data['status']],
            auth()->user(),
            $request,
        );

        $this->fcm->notifyUsers(
            $this->calendarUserIds(),
            'Estado de cita actualizado',
            $appointment->title.' → '.Appointment::STATUS_LABELS[$data['status']],
            ['appointment_id' => (string) $appointment->id, 'action' => 'status_changed'],
        );

        return back()->with('status', 'Estado actualizado.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);

        $this->activityLog->record('appointment.deleted', $appointment, ['title' => $appointment->title], auth()->user(), request());
        $appointment->delete();

        return redirect()->route('web.appointments.index')
            ->with('status', 'Cita eliminada.');
    }

    /**
     * Abort with 422 if there is a time overlap for the same creator.
     */
    private function checkConflicts(string $startAt, string $endAt, ?int $excludeId = null): void
    {
        $query = Appointment::query()
            ->where('created_by', auth()->id())
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            abort(422, 'Existe un conflicto de horario con otra cita en ese rango.');
        }
    }

    /** @return array<int> */
    private function calendarUserIds(): array
    {
        return \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'vendedor']))
            ->pluck('id')
            ->all();
    }
}
