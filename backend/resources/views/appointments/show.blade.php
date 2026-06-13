@extends('layouts.app')

@section('title', $appointment->title)
@section('subtitle', $appointment->start_at->format('d/m/Y H:i').' — '.$appointment->end_at->format('H:i'))

@section('actions')
    @can('update', $appointment)
        <a href="{{ route('web.appointments.edit', $appointment) }}" class="btn">
            <i data-lucide="pencil" class="w-4 h-4"></i> Editar
        </a>
        <form method="POST" action="{{ route('web.appointments.destroy', $appointment) }}"
              onsubmit="return confirm('¿Eliminar esta cita?')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Eliminar
            </button>
        </form>
    @endcan
    <a href="{{ route('web.appointments.index', ['year' => $appointment->start_at->year, 'month' => $appointment->start_at->month]) }}"
       class="btn">
        <i data-lucide="calendar" class="w-4 h-4"></i> Volver al calendario
    </a>
@endsection

@section('content')
<div class="invoice-grid">
    <div class="space-y-5">
        {{-- Main details --}}
        <section class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 style="margin:0">Detalles</h3>
                <span class="status" style="background:{{ $appointment->statusColor() }}1a; color:{{ $appointment->statusColor() }}; border:1px solid {{ $appointment->statusColor() }}44">
                    {{ $appointment->statusLabel() }}
                </span>
            </div>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-[13px]">
                <div>
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Inicio</dt>
                    <dd>{{ $appointment->start_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Fin</dt>
                    <dd>{{ $appointment->end_at->format('d/m/Y H:i') }}</dd>
                </div>
                @if($appointment->client_name)
                <div>
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Cliente</dt>
                    <dd>{{ $appointment->client_name }}</dd>
                </div>
                @endif
                @if($appointment->location)
                <div class="col-span-2">
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Ubicación</dt>
                    <dd class="flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-danger shrink-0"></i>
                        <span>{{ $appointment->location }}</span>
                    </dd>
                </div>
                @endif
                <div>
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Creado por</dt>
                    <dd>{{ $appointment->creator?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-on-surface-variant mb-0.5">Creado el</dt>
                    <dd>{{ $appointment->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>

            @if($appointment->service_description)
            <div class="mt-4 pt-4 border-t border-outline-variant/50">
                <p class="font-semibold text-[13px] text-on-surface-variant mb-1">Servicio a realizar</p>
                <p class="text-[13px] whitespace-pre-wrap">{{ $appointment->service_description }}</p>
            </div>
            @endif

            @if($appointment->observations)
            <div class="mt-4 pt-4 border-t border-outline-variant/50">
                <p class="font-semibold text-[13px] text-on-surface-variant mb-1">Observaciones</p>
                <p class="text-[13px] whitespace-pre-wrap">{{ $appointment->observations }}</p>
            </div>
            @endif
        </section>

        {{-- Map --}}
        @if($appointment->location)
        @php $encodedLocation = urlencode($appointment->location); @endphp
        <section class="card" style="padding:0; overflow:hidden;">
            {{-- Nav buttons bar --}}
            <div class="flex items-center gap-2 px-4 py-3 border-b border-outline-variant/50 flex-wrap">
                <i data-lucide="navigation" class="w-4 h-4 text-primary shrink-0"></i>
                <span class="text-[13px] font-semibold mr-1">Navegar con:</span>
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $encodedLocation }}"
                   target="_blank" rel="noopener"
                   class="btn" style="padding:6px 12px; font-size:12px; gap:6px;">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#4285F4"/><circle cx="12" cy="9" r="2.5" fill="white"/></svg>
                    Google Maps
                </a>
                <a href="https://waze.com/ul?q={{ $encodedLocation }}&navigate=yes"
                   target="_blank" rel="noopener"
                   class="btn" style="padding:6px 12px; font-size:12px; gap:6px;">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="#33ccff"><path d="M12 2a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 12 2zm0 17.5a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><circle cx="9.5" cy="10.5" r="1.25" fill="#33ccff"/><circle cx="14.5" cy="10.5" r="1.25" fill="#33ccff"/><path d="M8.5 13.5s1 2 3.5 2 3.5-2 3.5-2" stroke="#33ccff" stroke-width="1.2" fill="none" stroke-linecap="round"/></svg>
                    Waze
                </a>
                <a href="https://maps.apple.com/?daddr={{ $encodedLocation }}"
                   target="_blank" rel="noopener"
                   class="btn" style="padding:6px 12px; font-size:12px; gap:6px;">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="#666"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>
                    Apple Maps
                </a>
                <span class="ml-auto text-[11px] text-on-surface-variant truncate max-w-xs hidden sm:block">
                    {{ $appointment->location }}
                </span>
            </div>
            {{-- Embedded map (OpenStreetMap, no API key needed) --}}
            <div style="position:relative; height:300px;">
                <iframe
                    src="https://maps.google.com/maps?q={{ $encodedLocation }}&output=embed&z=15"
                    width="100%"
                    height="300"
                    style="border:0; display:block;"
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Mapa de ubicación: {{ e($appointment->location) }}">
                </iframe>
            </div>
        </section>
        @endif

        {{-- Contacts --}}
        @if(!empty($appointment->contacts))
        <section class="card">
            <h3>Contactos</h3>
            <div class="space-y-2">
                @foreach($appointment->contacts as $contact)
                <div class="flex items-start gap-4 py-2 border-b border-outline-variant/50 last:border-0 text-[13px]">
                    <div class="w-8 h-8 rounded-full bg-primary-soft text-primary font-bold text-[12px] flex items-center justify-center shrink-0">
                        {{ strtoupper(substr($contact['name'] ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold">{{ $contact['name'] ?? '—' }}</p>
                        @if(!empty($contact['phone']))<p class="text-on-surface-variant">{{ $contact['phone'] }}</p>@endif
                        @if(!empty($contact['email']))<p class="text-on-surface-variant">{{ $contact['email'] }}</p>@endif
                    </div>
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- History --}}
        <section class="card">
            <h3>Historial de cambios</h3>
            @forelse($logs as $log)
            <div class="flex items-start gap-3 py-2.5 border-b border-outline-variant/50 last:border-0 text-[13px]">
                <div class="w-7 h-7 rounded-full bg-surface-low text-on-surface-variant font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5">
                    {{ strtoupper(substr($log->user?->name ?? 'S', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <span class="font-semibold">{{ $log->user?->name ?? 'Sistema' }}</span>
                    <span class="text-on-surface-variant ml-1">{{ $log->action }}</span>
                    @if(!empty($log->properties['old_status']) && !empty($log->properties['new_status']))
                        <span class="text-on-surface-variant">·
                            {{ \App\Models\Appointment::STATUS_LABELS[$log->properties['old_status']] ?? $log->properties['old_status'] }}
                            → {{ \App\Models\Appointment::STATUS_LABELS[$log->properties['new_status']] ?? $log->properties['new_status'] }}
                        </span>
                    @endif
                </div>
                <span class="text-[11px] text-on-surface-variant shrink-0">{{ $log->created_at->format('d/m H:i') }}</span>
            </div>
            @empty
            <p class="text-[13px] text-on-surface-variant">Sin historial registrado.</p>
            @endforelse
        </section>
    </div>

    {{-- Sidebar --}}
    <aside class="space-y-4">

        {{-- Status change --}}
        @can('update', $appointment)
        <section class="card">
            <h3 style="margin-bottom:14px">Cambiar estado</h3>
            @php
                $statusMeta = [
                    'pending'     => ['label'=>'Pendiente',  'color'=>'#3b82f6', 'icon'=>'clock',         'desc'=>'Cita agendada, aún no iniciada'],
                    'in_progress' => ['label'=>'En curso',   'color'=>'#f59e0b', 'icon'=>'play-circle',    'desc'=>'Trabajo en proceso ahora mismo'],
                    'done'        => ['label'=>'Realizado',  'color'=>'#10b981', 'icon'=>'check-circle-2', 'desc'=>'Servicio completado con éxito'],
                    'urgent'      => ['label'=>'Urgente',    'color'=>'#ef4444', 'icon'=>'alert-triangle',  'desc'=>'Atención inmediata requerida'],
                    'priority'    => ['label'=>'Prioridad',  'color'=>'#8b5cf6', 'icon'=>'star',           'desc'=>'Alta importancia, atender pronto'],
                    'cancelled'   => ['label'=>'Cancelada',  'color'=>'#9ca3af', 'icon'=>'x-circle',       'desc'=>'Cita cancelada definitivamente'],
                ];
            @endphp
            <div class="space-y-2">
                @foreach($statusMeta as $key => $meta)
                <form method="POST" action="{{ route('web.appointments.status', $appointment) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit"
                        @if($appointment->status === $key) disabled @endif
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg border text-left transition-all
                               {{ $appointment->status === $key
                                   ? 'border-transparent cursor-default'
                                   : 'border-outline-variant hover:border-transparent hover:shadow-sm cursor-pointer bg-white' }}"
                        style="{{ $appointment->status === $key ? 'background:'.substr($meta['color'],0,7).'18; border-color:'.substr($meta['color'],0,7).'55;' : '' }}"
                        title="{{ $meta['desc'] }}">
                        <i data-lucide="{{ $meta['icon'] }}" class="w-4 h-4 shrink-0"
                           style="color:{{ $meta['color'] }}"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold leading-4" style="{{ $appointment->status === $key ? 'color:'.$meta['color'].';' : '' }}">
                                {{ $meta['label'] }}
                                @if($appointment->status === $key)
                                    <span class="text-[10px] font-normal ml-1 opacity-70">← actual</span>
                                @endif
                            </p>
                            <p class="text-[11px] text-on-surface-variant leading-3 mt-0.5">{{ $meta['desc'] }}</p>
                        </div>
                    </button>
                </form>
                @endforeach
            </div>
        </section>
        @endcan

        {{-- Info card --}}
        <section class="card">
            <h3 style="margin-bottom:10px">Información</h3>
            <dl class="space-y-2 text-[13px]">
                <div class="flex justify-between">
                    <dt class="text-on-surface-variant">Duración</dt>
                    <dd class="font-medium">{{ $appointment->start_at->diffForHumans($appointment->end_at, true) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-on-surface-variant">Creador</dt>
                    <dd class="font-medium">{{ $appointment->creator?->name ?? '—' }}</dd>
                </div>
                @if($appointment->client_name)
                <div class="flex justify-between">
                    <dt class="text-on-surface-variant">Cliente</dt>
                    <dd class="font-medium truncate max-w-[140px]">{{ $appointment->client_name }}</dd>
                </div>
                @endif
            </dl>
        </section>

        {{-- State legend --}}
        <section class="card">
            <h3 style="margin-bottom:10px;font-size:13px">Guía de estados</h3>
            <div class="space-y-1.5">
                @php
                    $flow = [
                        ['pending','Pendiente','Nueva cita sin iniciar'],
                        ['in_progress','En curso','Trabajo activo'],
                        ['done','Realizado','Completado'],
                        ['urgent','Urgente','Requiere atención ya'],
                        ['priority','Prioridad','Alta importancia'],
                        ['cancelled','Cancelada','No se realizará'],
                    ];
                @endphp
                @foreach($flow as [$key, $label, $desc])
                <div class="flex items-center gap-2 text-[12px]">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ \App\Models\Appointment::STATUS_COLORS[$key] }}"></span>
                    <span class="font-semibold w-20 shrink-0">{{ $label }}</span>
                    <span class="text-on-surface-variant">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </section>
    </aside>
</div>
@endsection
