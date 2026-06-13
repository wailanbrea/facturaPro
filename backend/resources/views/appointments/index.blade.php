@extends('layouts.app')

@section('title', 'Calendario')
@section('subtitle', 'Agenda compartida de citas y servicios')

@section('head')
<style>
/* Calendario basado en tabla con layout fijo: 7 columnas iguales, sin desbordes */
.cal-table {
    width: 100%;
    table-layout: fixed;      /* columnas de ancho idéntico, ignora el contenido */
    border-collapse: collapse;
    border: 1px solid #e2e1ed;
    border-radius: 10px;
    overflow: hidden;
    background: #e2e1ed;
}
.cal-table th {
    background: #f3f2fe;
    padding: 8px 4px;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #434655;
    border: 1px solid #e2e1ed;
}
.cal-table td {
    width: 14.2857%;          /* 100 / 7 */
    height: 116px;
    vertical-align: top;
    background: #fff;
    padding: 5px;
    position: relative;
    overflow: hidden;
    border: 1px solid #e2e1ed;
    box-sizing: border-box;
}
.cal-cell.other-month { background: #faf8ff; }
.cal-cell.today { background: #eef2ff; }
.cal-cell.today .cal-day-num {
    background: #0037b0; color: #fff; border-radius: 50%;
    width: 22px; height: 22px;
    display: inline-flex; align-items: center; justify-content: center;
}
.cal-day-num { font-size: 12px; font-weight: 600; color: #1a1b23; display: inline-block; line-height: 1; }
.other-month .cal-day-num { color: #9ca3af; }
.cal-events { margin-top: 4px; display: flex; flex-direction: column; gap: 2px; }
.cal-event {
    display: block;
    max-width: 100%;
    font-size: 10.5px;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
    line-height: 1.4;
    text-decoration: none;
}
.cal-event:hover { opacity: .85; }
.cal-more {
    font-size: 10px; color: #6b7280; padding: 0 2px;
    cursor: pointer; background: none; border: none;
    text-align: left; line-height: 1.6; display: block;
}
.cal-more:hover { color: #0037b0; font-weight: 600; }
.cal-add-btn {
    position: absolute; top: 3px; right: 4px;
    width: 18px; height: 18px; border-radius: 50%;
    background: #e8e7f3; color: #434655;
    font-size: 13px; line-height: 18px; text-align: center;
    display: none; cursor: pointer; text-decoration: none;
}
.cal-cell:hover .cal-add-btn { display: block; }
</style>
@endsection

@section('actions')
    <a href="{{ route('web.appointments.create') }}" class="btn primary">
        <i data-lucide="plus" class="w-4 h-4"></i> Nueva cita
    </a>
@endsection

@section('content')
@php
    use Carbon\Carbon;
    use App\Models\Appointment;

    $prevMonth = Carbon::create($year, $month, 1)->subMonth();
    $nextMonth = Carbon::create($year, $month, 1)->addMonth();
    $monthName = Carbon::create($year, $month, 1)->locale('es')->isoFormat('MMMM YYYY');

    // Build calendar grid (always 6 weeks for stability)
    $gridStart = Carbon::create($year, $month, 1)->startOfWeek(Carbon::MONDAY);
    $gridEnd = $gridStart->copy()->addWeeks(6);

    $byDay = $appointments->groupBy(fn ($a) => $a->start_at->toDateString());
    $today = now()->toDateString();
@endphp

{{-- Month nav --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('web.appointments.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}"
           class="p-2 rounded-lg border border-outline-variant hover:bg-surface-low">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
        <h2 class="text-lg font-bold capitalize text-on-surface w-40 text-center">{{ $monthName }}</h2>
        <a href="{{ route('web.appointments.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}"
           class="p-2 rounded-lg border border-outline-variant hover:bg-surface-low">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
        <a href="{{ route('web.appointments.index') }}"
           class="btn text-sm">Hoy</a>
    </div>
    {{-- Status legend --}}
    <div class="hidden md:flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[12px] font-medium">
        @foreach(Appointment::STATUS_LABELS as $key => $label)
            <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full inline-block shrink-0" style="background:{{ Appointment::STATUS_COLORS[$key] }}"></span>
                {{ $label }}
            </span>
        @endforeach
    </div>
</div>

<div class="flex gap-5 items-start">
    {{-- Calendar --}}
    <div class="flex-1 min-w-0">
        <table class="cal-table">
            <thead>
                <tr>
                    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)
                        <th>{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $cursor = $gridStart->copy(); @endphp
                @for($week = 0; $week < 6; $week++)
                    <tr>
                        @for($dow = 0; $dow < 7; $dow++)
                            @php
                                $dateStr = $cursor->toDateString();
                                $isCurrentMonth = $cursor->month === $month;
                                $isToday = $dateStr === $today;
                                $dayEvents = $byDay->get($dateStr, collect());
                                $visible = $dayEvents->take(2);
                                $remaining = $dayEvents->count() - $visible->count();
                            @endphp
                            <td class="cal-cell {{ !$isCurrentMonth ? 'other-month' : '' }} {{ $isToday ? 'today' : '' }}"
                                data-date="{{ $dateStr }}">
                                <span class="cal-day-num">{{ $cursor->day }}</span>
                                <a href="{{ route('web.appointments.create', ['date' => $dateStr]) }}"
                                   class="cal-add-btn" title="Nueva cita">+</a>
                                <div class="cal-events">
                                    @foreach($visible as $appt)
                                        <a href="{{ route('web.appointments.show', $appt) }}"
                                           class="cal-event"
                                           style="background:{{ $appt->statusColor() }}"
                                           title="{{ $appt->title }} ({{ $appt->start_at->format('H:i') }})">
                                            {{ $appt->start_at->format('H:i') }} {{ $appt->title }}
                                        </a>
                                    @endforeach
                                    @if($remaining > 0)
                                        <button type="button" class="cal-more"
                                                onclick="showDayModal('{{ $dateStr }}')">
                                            +{{ $remaining }} más
                                        </button>
                                    @endif
                                </div>
                            </td>
                            @php $cursor->addDay(); @endphp
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    {{-- Side list: upcoming appointments --}}
    <aside class="hidden xl:block w-72 shrink-0">
        <div class="card">
            <h3 class="text-[14px] font-bold mb-3">Próximas citas</h3>
            @php
                $upcoming = $appointments->filter(fn ($a) => $a->start_at >= now())->take(8);
            @endphp
            @forelse($upcoming as $appt)
                <a href="{{ route('web.appointments.show', $appt) }}"
                   class="flex items-start gap-3 py-2.5 border-b border-outline-variant/50 last:border-0 hover:bg-surface-low -mx-3 px-3 rounded transition-colors">
                    <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background:{{ $appt->statusColor() }}"></span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold truncate">{{ $appt->title }}</p>
                        <p class="text-[11px] text-on-surface-variant">{{ $appt->start_at->format('d/m H:i') }}</p>
                        @if($appt->client_name)
                            <p class="text-[11px] text-on-surface-variant truncate">{{ $appt->client_name }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <p class="text-[13px] text-on-surface-variant">No hay citas próximas.</p>
            @endforelse
        </div>
    </aside>
</div>

{{-- Day detail modal --}}
<div id="day-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDayModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-outline-variant/50">
            <h2 id="day-modal-title" class="font-bold text-[16px]"></h2>
            <button onclick="closeDayModal()" class="p-1.5 rounded-lg hover:bg-surface-low text-on-surface-variant">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div id="day-modal-body" class="overflow-y-auto flex-1 px-5 py-3 space-y-2"></div>
    </div>
</div>
@endsection

@php
    $allAppointmentsJson = $appointments->map(fn($a) => [
        'id'          => $a->id,
        'title'       => $a->title,
        'date'        => $a->start_at->toDateString(),
        'start'       => $a->start_at->format('H:i'),
        'end'         => $a->end_at->format('H:i'),
        'status'      => $a->status,
        'statusLabel' => $a->statusLabel(),
        'color'       => $a->statusColor(),
        'client'      => $a->client_name,
        'location'    => $a->location,
        'url'         => route('web.appointments.show', $a),
    ])->values()->toJson();
@endphp

@section('scripts')
<script>
const allAppointments = @json(json_decode($allAppointmentsJson));

function showDayModal(dateStr) {
    const dayAppts = allAppointments.filter(a => a.date === dateStr);
    const date = new Date(dateStr + 'T00:00:00');
    const label = date.toLocaleDateString('es-ES', { weekday:'long', day:'numeric', month:'long' });

    document.getElementById('day-modal-title').textContent =
        label.charAt(0).toUpperCase() + label.slice(1);

    const body = document.getElementById('day-modal-body');
    body.innerHTML = dayAppts.map(a => `
        <a href="${a.url}" class="flex items-start gap-3 p-3 rounded-xl border border-outline-variant/50 hover:bg-surface-low transition-colors block no-underline" style="text-decoration:none;color:inherit">
            <span class="w-2.5 h-2.5 rounded-full mt-1 shrink-0" style="background:${a.color};display:inline-block"></span>
            <div style="flex:1;min-width:0">
                <p style="font-weight:600;font-size:13px;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${a.title}</p>
                <p style="font-size:11px;color:#434655;margin:2px 0 0">${a.start} – ${a.end}${a.client ? ' · ' + a.client : ''}</p>
                ${a.location ? `<p style="font-size:11px;color:#434655;margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">📍 ${a.location}</p>` : ''}
            </div>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;white-space:nowrap;background:${a.color}18;color:${a.color};border:1px solid ${a.color}44">
                ${a.statusLabel}
            </span>
        </a>
    `).join('');

    const modal = document.getElementById('day-modal');
    modal.classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function closeDayModal() {
    document.getElementById('day-modal').classList.add('hidden');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDayModal(); });
</script>
@endsection
