<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\DeviceToken;
use App\Services\ActivityLogService;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly FcmService $fcm,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $appointments = Appointment::query()
            ->with('creator:id,name', 'client:id,name')
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->get();

        return response()->json(['data' => $appointments]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'service_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $appointment = Appointment::query()->create([
            ...$data,
            'client_name' => ($data['client_name'] ?? null) ?: $client?->name,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? Appointment::STATUS_PENDING,
        ]);

        $this->activityLog->record('appointment.created', $appointment, ['title' => $appointment->title], $request->user(), $request);
        $this->fcm->notifyUsers(
            $this->calendarUserIds(),
            'Nueva cita agendada',
            $appointment->title.' — '.$appointment->start_at->format('d/m H:i'),
            ['appointment_id' => (string) $appointment->id, 'action' => 'created'],
        );

        return response()->json(['data' => $appointment->load('creator:id,name', 'client:id,name')], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json(['data' => $appointment->load('creator:id,name', 'client:id,name')]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->canEdit($user, $appointment)) {
            return response()->json(['message' => 'No tienes permiso para editar esta cita.'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'service_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'status' => ['sometimes', 'required', Rule::in(Appointment::STATUSES)],
        ]);

        $oldStatus = $appointment->status;
        $appointment->update($data);

        $this->activityLog->record('appointment.updated', $appointment, ['old_status' => $oldStatus, 'new_status' => $appointment->status], $user, $request);

        if ($oldStatus !== $appointment->status) {
            $this->fcm->notifyUsers(
                $this->calendarUserIds(),
                'Cita actualizada',
                $appointment->title.' — '.Appointment::STATUS_LABELS[$appointment->status],
                ['appointment_id' => (string) $appointment->id, 'action' => 'status_changed'],
            );
        }

        return response()->json(['data' => $appointment->load('creator:id,name', 'client:id,name')]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->canEdit($user, $appointment)) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta cita.'], 403);
        }

        $this->activityLog->record('appointment.deleted', $appointment, ['title' => $appointment->title], $user, $request);
        $appointment->delete();

        return response()->json(['message' => 'Cita eliminada.']);
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ]);

        DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android'],
        );

        return response()->json(['message' => 'Token registrado.']);
    }

    private function canEdit(\App\Models\User $user, Appointment $appointment): bool
    {
        return $user->hasPermission('gestionar_citas')
            || $user->getKey() === $appointment->created_by
            || $user->roles()->where('slug', 'admin')->exists();
    }

    /** @return array<int> */
    private function calendarUserIds(): array
    {
        return \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'facturador', 'operador']))
            ->pluck('id')
            ->all();
    }
}
