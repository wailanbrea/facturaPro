@extends('layouts.app')

@section('title', 'Reportes')
@section('subtitle', 'Resumen operativo con filtros por fecha y moneda')

@section('actions')
<form method="GET" class="flex items-center gap-2 flex-wrap">
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <input name="date_from" type="date" value="{{ $filters['dateFrom'] }}"
               class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] w-[130px] text-on-surface">
    </div>
    <span class="text-on-surface-variant text-[13px]">→</span>
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <input name="date_to" type="date" value="{{ $filters['dateTo'] }}"
               class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] w-[130px] text-on-surface">
    </div>
    <div class="flex items-center gap-1.5 bg-white border border-outline-variant/70 rounded-lg px-3 h-[38px]">
        <i data-lucide="coins" class="w-3.5 h-3.5 text-on-surface-variant shrink-0"></i>
        <select name="currency_code" class="border-0 outline-none focus:ring-0 bg-transparent text-[13px] text-on-surface pr-2">
            <option value="">Todas las monedas</option>
            @foreach($currencies as $currency)
                <option value="{{ $currency->code }}" @selected($filters['currencyCode'] === $currency->code)>{{ $currency->code }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn primary" style="height:38px;padding:0 16px">
        <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrar
    </button>
    @if($filters['dateFrom'] || $filters['dateTo'] || $filters['currencyCode'])
        <a href="{{ route('web.reports.index') }}" class="btn" style="height:38px;padding:0 12px" title="Limpiar filtros">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </a>
    @endif
</form>
@endsection

@section('content')

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-primary-soft-2 flex items-center justify-center shrink-0">
            <i data-lucide="file-text" class="w-5 h-5 text-primary"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Facturas</div>
            <div class="text-[26px] font-bold text-primary leading-7 mt-0.5">{{ (int) $overview->invoices_count }}</div>
        </div>
    </div>

    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl {{ $overview->overdue_count > 0 ? 'bg-danger-soft' : 'bg-success-soft' }} flex items-center justify-center shrink-0">
            <i data-lucide="alert-triangle" class="w-5 h-5 {{ $overview->overdue_count > 0 ? 'text-danger' : 'text-success' }}"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Vencidas</div>
            <div class="text-[26px] font-bold {{ $overview->overdue_count > 0 ? 'text-danger' : 'text-success' }} leading-7 mt-0.5">{{ (int) $overview->overdue_count }}</div>
        </div>
    </div>

    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-secondary-soft flex items-center justify-center shrink-0">
            <i data-lucide="landmark" class="w-5 h-5 text-secondary"></i>
        </div>
        <div>
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Monedas</div>
            <div class="text-[26px] font-bold text-secondary leading-7 mt-0.5">{{ $totalsByCurrency->count() }}</div>
        </div>
    </div>

    <div class="card flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-surface-mid flex items-center justify-center shrink-0">
            <i data-lucide="calendar-range" class="w-5 h-5 text-on-surface-variant"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant">Rango</div>
            <div class="text-[13px] font-semibold text-on-surface leading-5 mt-0.5 truncate">
                {{ $filters['dateFrom'] ?: 'Inicio' }}
            </div>
            <div class="text-[12px] text-on-surface-variant">{{ $filters['dateTo'] ?: 'Hoy' }}</div>
        </div>
    </div>
</div>

{{-- Totales consolidados --}}
@if($canShowUnifiedMoneyTotals && $totals)
    <div class="card mb-6">
        <div class="flex items-center gap-2 mb-4">
            <i data-lucide="trending-up" class="w-4 h-4 text-primary"></i>
            <h3 style="margin:0;font-size:15px">Totales consolidados</h3>
            <span class="text-[12px] text-on-surface-variant">Moneda seleccionada: {{ $totals->currency_code }}</span>
            <span class="ml-auto text-[12px] font-semibold bg-primary-soft-2 text-primary px-2.5 py-0.5 rounded-full">{{ $totals->currency_code }}</span>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-xl border border-outline-variant/50 p-4" style="background:linear-gradient(135deg,#eef2ff,#fff)">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="circle-dollar-sign" class="w-4 h-4 text-primary"></i>
                    <span class="text-[12px] font-semibold text-on-surface-variant uppercase tracking-wide">Facturado</span>
                </div>
                <div class="text-[22px] font-bold text-primary">{{ $totals->currency_symbol }} {{ number_format((float) $totals->total_invoiced, 2) }}</div>
            </div>
            <div class="rounded-xl border border-success/20 p-4" style="background:linear-gradient(135deg,#ecfdf5,#fff)">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-success"></i>
                    <span class="text-[12px] font-semibold text-on-surface-variant uppercase tracking-wide">Cobrado</span>
                </div>
                <div class="text-[22px] font-bold text-success">{{ $totals->currency_symbol }} {{ number_format((float) $totals->total_collected, 2) }}</div>
            </div>
            <div class="rounded-xl border border-warning/20 p-4" style="background:linear-gradient(135deg,#fffbeb,#fff)">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="clock" class="w-4 h-4 text-warning"></i>
                    <span class="text-[12px] font-semibold text-on-surface-variant uppercase tracking-wide">Pendiente</span>
                </div>
                <div class="text-[22px] font-bold text-warning">{{ $totals->currency_symbol }} {{ number_format((float) $totals->total_pending, 2) }}</div>
            </div>
        </div>
    </div>
@else
    <div class="alert mb-6">
        <div class="flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
            Los montos se muestran desglosados por moneda para evitar sumar importes incompatibles entre divisas.
        </div>
    </div>
@endif

{{-- Fila: Por moneda + Por estado --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <section class="card" style="padding:0;overflow:hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant/50">
            <i data-lucide="coins" class="w-4 h-4 text-primary"></i>
            <h3 style="margin:0;font-size:15px">Totales agrupados por moneda</h3>
        </div>
        <div style="overflow-x:auto">
            <table class="table" style="border:0;border-radius:0;box-shadow:none">
                <thead>
                    <tr>
                        <th>Moneda</th>
                        <th class="right">Facturas</th>
                        <th class="right">Facturado</th>
                        <th class="right">Cobrado</th>
                        <th class="right">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($totalsByCurrency as $row)
                    <tr>
                        <td><span class="font-semibold text-primary">{{ $row->currency_code }}</span></td>
                        <td class="right">{{ $row->invoices_count }}</td>
                        <td class="right text-[13px]">{{ $row->currency_symbol }} {{ number_format((float) $row->total_invoiced, 2) }}</td>
                        <td class="right text-[13px] text-success font-medium">{{ $row->currency_symbol }} {{ number_format((float) $row->total_collected, 2) }}</td>
                        <td class="right text-[13px] {{ (float)$row->total_pending > 0 ? 'text-warning font-medium' : '' }}">{{ $row->currency_symbol }} {{ number_format((float) $row->total_pending, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted text-center py-8">Sin facturas para los filtros aplicados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="padding:0;overflow:hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant/50">
            <i data-lucide="layers" class="w-4 h-4 text-primary"></i>
            <h3 style="margin:0;font-size:15px">Por estado</h3>
        </div>
        <div style="overflow-x:auto">
            <table class="table" style="border:0;border-radius:0;box-shadow:none">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Mon.</th>
                        <th class="right">Fact.</th>
                        <th class="right">Facturado</th>
                        <th class="right">Cobrado</th>
                        <th class="right">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byStatus as $row)
                    <tr>
                        <td><span class="status {{ $row->status }}">{{ \App\Support\InvoiceStatusLabel::label($row->status) }}</span></td>
                        <td class="text-[12px] text-on-surface-variant">{{ $row->currency_code }}</td>
                        <td class="right">{{ $row->invoices_count }}</td>
                        <td class="right text-[13px]">{{ $row->currency_symbol }} {{ number_format((float) $row->total_invoiced, 2) }}</td>
                        <td class="right text-[13px] text-success font-medium">{{ $row->currency_symbol }} {{ number_format((float) $row->total_collected, 2) }}</td>
                        <td class="right text-[13px] {{ (float)$row->total_pending > 0 ? 'text-warning font-medium' : '' }}">{{ $row->currency_symbol }} {{ number_format((float) $row->total_pending, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center py-8">Sin datos por estado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- Fila: Por cliente + Por fecha --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

    <section class="card" style="padding:0;overflow:hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant/50">
            <i data-lucide="users" class="w-4 h-4 text-primary"></i>
            <h3 style="margin:0;font-size:15px">Por cliente</h3>
        </div>
        <div style="overflow-x:auto">
            <table class="table" style="border:0;border-radius:0;box-shadow:none">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Mon.</th>
                        <th class="right">Fact.</th>
                        <th class="right">Facturado</th>
                        <th class="right">Cobrado</th>
                        <th class="right">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byClient as $row)
                    <tr>
                        <td class="font-medium text-[13px]">{{ $row->client_name }}</td>
                        <td class="text-[12px] text-on-surface-variant">{{ $row->currency_code }}</td>
                        <td class="right">{{ $row->invoices_count }}</td>
                        <td class="right text-[13px]">{{ $row->currency_symbol }} {{ number_format((float) $row->total_invoiced, 2) }}</td>
                        <td class="right text-[13px] text-success font-medium">{{ $row->currency_symbol }} {{ number_format((float) $row->total_collected, 2) }}</td>
                        <td class="right text-[13px] {{ (float)$row->total_pending > 0 ? 'text-warning font-medium' : '' }}">{{ $row->currency_symbol }} {{ number_format((float) $row->total_pending, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center py-8">Sin datos por cliente.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="padding:0;overflow:hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant/50">
            <i data-lucide="calendar-days" class="w-4 h-4 text-primary"></i>
            <h3 style="margin:0;font-size:15px">Por fecha</h3>
            <span class="ml-auto text-[11px] text-on-surface-variant">{{ $byDate->count() }} días</span>
        </div>
        <div style="overflow-x:auto;max-height:340px;overflow-y:auto">
            <table class="table" style="border:0;border-radius:0;box-shadow:none">
                <thead style="position:sticky;top:0;z-index:1">
                    <tr>
                        <th>Fecha</th>
                        <th>Mon.</th>
                        <th class="right">Fact.</th>
                        <th class="right">Facturado</th>
                        <th class="right">Cobrado</th>
                        <th class="right">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byDate as $row)
                    <tr>
                        <td class="text-[13px] font-medium">{{ \Illuminate\Support\Carbon::parse($row->invoice_day)->format('d/m/Y') }}</td>
                        <td class="text-[12px] text-on-surface-variant">{{ $row->currency_code }}</td>
                        <td class="right">{{ $row->invoices_count }}</td>
                        <td class="right text-[13px]">{{ $row->currency_symbol }} {{ number_format((float) $row->total_invoiced, 2) }}</td>
                        <td class="right text-[13px] text-success font-medium">{{ $row->currency_symbol }} {{ number_format((float) $row->total_collected, 2) }}</td>
                        <td class="right text-[13px] {{ (float)$row->total_pending > 0 ? 'text-warning font-medium' : '' }}">{{ $row->currency_symbol }} {{ number_format((float) $row->total_pending, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center py-8">Sin movimientos para el rango seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- Facturas vencidas --}}
@if($overdueInvoices->isNotEmpty())
<section class="card" style="padding:0;overflow:hidden;border-color:#fecaca">
    <div class="flex items-center gap-2 px-5 py-4 border-b border-red-100" style="background:#fff5f5">
        <i data-lucide="alert-circle" class="w-4 h-4 text-danger"></i>
        <h3 style="margin:0;font-size:15px;color:var(--danger)">Facturas vencidas</h3>
        <span class="ml-auto text-[12px] font-bold bg-danger-soft text-danger px-2.5 py-0.5 rounded-full">{{ $overdueInvoices->count() }}</span>
    </div>
    <div style="overflow-x:auto">
        <table class="table" style="border:0;border-radius:0;box-shadow:none">
            <thead>
                <tr>
                    <th>No. Factura</th>
                    <th>Cliente</th>
                    <th>Emisión</th>
                    <th>Vence</th>
                    <th>Mon.</th>
                    <th class="right">Total</th>
                    <th class="right">Pendiente</th>
                </tr>
            </thead>
            <tbody>
            @foreach($overdueInvoices as $invoice)
                <tr>
                    <td>
                        <a href="{{ route('web.invoices.show', $invoice) }}" class="font-semibold text-primary hover:underline text-[13px]">
                            {{ $invoice->invoice_number ?: 'BORRADOR-'.$invoice->id }}
                        </a>
                    </td>
                    <td class="text-[13px]">{{ $invoice->client_name }}</td>
                    <td class="text-[13px] text-on-surface-variant">{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                    <td class="text-[13px] font-medium text-danger">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                    <td class="text-[12px] text-on-surface-variant">{{ $invoice->currency_code }}</td>
                    <td class="right text-[13px]">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->total, 2) }}</td>
                    <td class="right text-[13px] font-bold text-danger">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->balance_due, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@else
<section class="card" style="padding:0;overflow:hidden">
    <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant/50">
        <i data-lucide="alert-circle" class="w-4 h-4 text-primary"></i>
        <h3 style="margin:0;font-size:15px">Facturas vencidas</h3>
    </div>
    <div class="flex items-center gap-3 px-5 py-8 text-success">
        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        <span class="text-[14px] font-medium">No hay facturas vencidas para los filtros aplicados.</span>
    </div>
</section>
@endif

@endsection
