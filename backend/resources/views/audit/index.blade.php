@extends('layouts.app')

@section('title', 'Auditoria')
@section('subtitle', 'Registro completo de acciones realizadas en el sistema')

@php
$accionesEs = [
    'invoice.created' => 'Factura creada',
    'invoice.updated' => 'Factura actualizada',
    'invoice.deleted' => 'Factura eliminada',
    'invoice.issued' => 'Factura emitida',
    'invoice.cancelled' => 'Factura anulada',
    'invoice.payment_recorded' => 'Pago registrado',
    'invoice.status_changed' => 'Estado de factura cambiado',
    'appointment.created' => 'Cita creada',
    'appointment.updated' => 'Cita actualizada',
    'appointment.deleted' => 'Cita eliminada',
    'appointment.status_changed' => 'Estado de cita cambiado',
    'client.created' => 'Cliente creado',
    'client.updated' => 'Cliente actualizado',
    'client.deleted' => 'Cliente eliminado',
    'report.created' => 'Informe creado',
    'report.updated' => 'Informe actualizado',
    'report.deleted' => 'Informe eliminado',
    'report.issued' => 'Informe emitido',
    'report.cancelled' => 'Informe anulado',
    'user.created' => 'Usuario creado',
    'user.updated' => 'Usuario actualizado',
    'user.deleted' => 'Usuario eliminado',
    'user.login' => 'Inicio de sesion',
    'user.logout' => 'Cierre de sesion',
    'settings.updated' => 'Configuracion actualizada',
];
@endphp

@section('actions')
<form method="GET" class="flex items-center gap-2 flex-wrap">
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="search" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <input name="search" type="text" placeholder="Usuario o accion..." value="{{ request('search') }}"
               class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] w-[160px] text-on-surface">
    </div>
    <select name="action" class="bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px] text-[13px] text-on-surface focus:outline-none">
        <option value="">Todas las acciones</option>
        @foreach($actions as $action => $total)
            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $accionesEs[$action] ?? $action }} ({{ $total }})</option>
        @endforeach
    </select>
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <input name="date_from" type="date" value="{{ request('date_from') }}"
               class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] w-[130px]">
    </div>
    <span class="text-on-surface-variant text-[13px]">a</span>
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <input name="date_to" type="date" value="{{ request('date_to') }}"
               class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] w-[130px]">
    </div>
    <button type="submit" class="btn primary" style="height:38px;padding:0 16px">
        <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrar
    </button>
    @if(request('search') || request('action') || request('date_from') || request('date_to'))
        <a href="{{ route('web.audit.index') }}" class="btn" style="height:38px;padding:0 12px">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </a>
    @endif
</form>
@endsection

@php
$estadosEs = [
    'draft'          => 'Borrador',
    'issued'         => 'Emitida',
    'paid'           => 'Pagada',
    'partially_paid' => 'Pago parcial',
    'overdue'        => 'Vencida',
    'cancelled'      => 'Anulada',
    'accepted'       => 'Aceptado',
    'converted'      => 'Convertido',
    'pending'        => 'Pendiente',
    'in_progress'    => 'En curso',
    'done'           => 'Realizado',
    'urgent'         => 'Urgente',
    'priority'       => 'Prioridad',
    'active'         => 'Activo',
    'inactive'       => 'Inactivo',
    'created'        => 'creada',
    'updated'        => 'actualizada',
    'deleted'        => 'eliminada',
];
@endphp

@section('content')

{{-- Stats rapidos --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-primary-soft-2 flex items-center justify-center shrink-0">
            <i data-lucide="activity" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Total eventos</div>
            <div class="text-[24px] font-bold text-primary leading-7 mt-0.5">{{ number_format($logs->total()) }}</div>
        </div>
    </div>
    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-surface-mid flex items-center justify-center shrink-0">
            <i data-lucide="layers" class="w-5 h-5 text-on-surface-variant"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Tipos de accion</div>
            <div class="text-[24px] font-bold text-on-surface leading-7 mt-0.5">{{ $actions->count() }}</div>
        </div>
    </div>
    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-surface-mid flex items-center justify-center shrink-0">
            <i data-lucide="file-text" class="w-5 h-5 text-on-surface-variant"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Esta pagina</div>
            <div class="text-[24px] font-bold text-on-surface leading-7 mt-0.5">{{ $logs->count() }}</div>
        </div>
    </div>
    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-surface-mid flex items-center justify-center shrink-0">
            <i data-lucide="book-open" class="w-5 h-5 text-on-surface-variant"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Paginas</div>
            <div class="text-[24px] font-bold text-on-surface leading-7 mt-0.5">{{ $logs->lastPage() }}</div>
        </div>
    </div>
</div>

<section class="card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
        <table class="table" style="border:0;border-radius:0;box-shadow:none">
            <thead>
                <tr>
                    <th style="width:160px">Fecha / Hora</th>
                    <th style="width:160px">Usuario</th>
                    <th style="width:180px">Accion</th>
                    <th>Objeto</th>
                    <th>Detalles</th>
                    <th style="width:120px">IP</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $actionParts = explode('.', $log->action);
                    $module = $actionParts[0] ?? '';
                    $verb   = $actionParts[1] ?? '';
                    $badgeClass = match($verb) {
                        'created'        => 'bg-success-soft text-success',
                        'updated',
                        'status_changed' => 'bg-warning-soft text-warning',
                        'deleted',
                        'cancelled',
                        'issued'         => 'bg-danger-soft text-danger',
                        'login'          => 'bg-primary-soft-2 text-primary',
                        default          => 'bg-surface-mid text-on-surface-variant',
                    };
                    $subjectLabel = match($module) {
                        'invoice'     => 'Factura',
                        'appointment' => 'Cita',
                        'client'      => 'Cliente',
                        'report'      => 'Informe',
                        'user'        => 'Usuario',
                        default       => ucfirst($module),
                    };
                @endphp
                <tr>
                    <td class="text-[12px] text-on-surface-variant whitespace-nowrap">
                        {{ $log->created_at->format('d/m/Y') }}<br>
                        <span class="font-semibold text-on-surface">{{ $log->created_at->format('H:i:s') }}</span>
                    </td>
                    <td>
                        @if($log->user)
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-primary-soft text-primary font-semibold text-[11px] flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr($log->user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-[13px] font-medium leading-4">{{ $log->user->name }}</div>
                                    <div class="text-[11px] text-on-surface-variant">{{ $log->user->email }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-[12px] text-on-surface-variant italic">Sistema</span>
                        @endif
                    </td>
                    <td>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $badgeClass }}">
                            {{ $accionesEs[$log->action] ?? str_replace(['.', '_'], [' > ', ' '], $log->action) }}
                        </span>
                    </td>
                    <td class="text-[13px]">
                        @if($log->subject_id)
                            <span class="font-medium">{{ $subjectLabel }}</span>
                            <span class="text-on-surface-variant ml-1">#{{ $log->subject_id }}</span>
                        @else
                            <span class="text-on-surface-variant">-</span>
                        @endif
                    </td>
                    <td class="text-[12px] text-on-surface-variant max-w-[280px]">
                        @if($log->properties)
                            @php $props = is_array($log->properties) ? $log->properties : json_decode($log->properties, true) ?? []; @endphp
                            <div class="flex flex-wrap gap-1">
                                @foreach($props as $key => $value)
                                    @php
                                        $keyEs = match($key) {
                                            'title'       => 'titulo',
                                            'old_status'  => 'estado anterior',
                                            'new_status'  => 'estado nuevo',
                                            'invoice_number' => 'numero',
                                            'client_name' => 'cliente',
                                            'amount'      => 'monto',
                                            'note'        => 'nota',
                                            'name'        => 'nombre',
                                            'email'       => 'correo',
                                            default       => str_replace('_', ' ', $key),
                                        };
                                        $valEs = is_string($value)
                                            ? ($estadosEs[$value] ?? Str::limit($value, 35))
                                            : json_encode($value);
                                    @endphp
                                    <span class="bg-surface-low rounded px-1.5 py-0.5 text-[11px]">
                                        <span class="font-medium">{{ $keyEs }}:</span> {{ $valEs }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-on-surface-variant">-</span>
                        @endif
                    </td>
                    <td class="text-[12px] font-mono text-on-surface-variant">{{ $log->ip_address ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-12">
                        <div class="flex flex-col items-center gap-2 text-on-surface-variant">
                            <i data-lucide="inbox" class="w-8 h-8 opacity-40"></i>
                            <span class="text-[14px]">No hay registros para los filtros aplicados.</span>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="px-5 py-4 border-t border-outline-variant/50 flex items-center justify-between gap-4">
            <span class="text-[13px] text-on-surface-variant">
                Mostrando {{ $logs->firstItem() }}-{{ $logs->lastItem() }} de {{ number_format($logs->total()) }} registros
            </span>
            <div class="flex items-center gap-1">
                @if($logs->onFirstPage())
                    <span class="btn opacity-40 cursor-not-allowed" style="height:34px;padding:0 10px">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}" class="btn" style="height:34px;padding:0 10px">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                @endif
                <span class="text-[13px] px-3 font-medium">{{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
                @if($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}" class="btn" style="height:34px;padding:0 10px">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                @else
                    <span class="btn opacity-40 cursor-not-allowed" style="height:34px;padding:0 10px">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </span>
                @endif
            </div>
        </div>
    @endif
</section>
@endsection
