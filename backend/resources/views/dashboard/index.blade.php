@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Resumen financiero y operativo')

@section('actions')
    <button class="inline-flex items-center gap-2 bg-white border border-outline-variant text-on-surface font-semibold text-[13px] rounded-lg px-3.5 py-2 hover:bg-surface-low">
        <i data-lucide="user-plus" class="w-4 h-4"></i>
        Nuevo cliente
    </button>
    <a href="{{ route('web.invoices.create') }}"
       class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover transition-colors text-white font-semibold text-[13px] rounded-lg px-3.5 py-2 shadow-sm">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nueva factura
    </a>
@endsection

@php
    use App\Support\InvoiceStatusLabel;

    $currencySymbol = $recentInvoices->first()->currency_symbol ?? 'RD$';
    $trend = $monthlyTrend;
    $trendSign = $trend === null ? null : ($trend >= 0 ? '+' : '');

    $statusStyles = [
        'draft' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'chart' => '#94a3b8'],
        'issued' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'chart' => '#1d4ed8'],
        'partially_paid' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'chart' => '#f59e0b'],
        'paid' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'chart' => '#10b981'],
        'overdue' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'chart' => '#ef4444'],
        'cancelled' => ['bg' => 'bg-slate-200', 'text' => 'text-slate-800', 'chart' => '#475569'],
    ];
@endphp

@section('content')
{{-- KPIs --}}
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
    {{-- Facturado (mes) --}}
    <div class="bg-white rounded-xl border border-outline-variant/60 p-5 shadow-card">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[12px] font-semibold uppercase tracking-wider text-on-surface-variant">Total facturado (mes)</p>
            <div class="w-9 h-9 rounded-lg bg-primary-soft-2 text-primary flex items-center justify-center">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
        </div>
        <p class="text-[26px] font-bold text-on-surface leading-7 tabular-nums">{{ $currencySymbol }}{{ number_format($totalBilledMonth, 2) }}</p>
        @if($trend !== null)
            <p class="mt-2 inline-flex items-center gap-1 text-[12px] font-semibold rounded-md px-2 py-0.5
                      {{ $trend >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                <i data-lucide="{{ $trend >= 0 ? 'arrow-up-right' : 'arrow-down-right' }}" class="w-3 h-3"></i>
                {{ $trendSign }}{{ number_format($trend, 1) }}% vs mes anterior
            </p>
        @else
            <p class="mt-2 text-[12px] text-on-surface-variant">Sin datos de mes anterior</p>
        @endif
    </div>

    {{-- Total cobrado --}}
    <div class="bg-white rounded-xl border border-outline-variant/60 p-5 shadow-card">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[12px] font-semibold uppercase tracking-wider text-on-surface-variant">Total cobrado</p>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="wallet" class="w-4 h-4"></i>
            </div>
        </div>
        <p class="text-[26px] font-bold text-on-surface leading-7 tabular-nums">{{ $currencySymbol }}{{ number_format($totalCollected, 2) }}</p>
        <p class="mt-2 text-[12px] font-semibold text-emerald-700">{{ number_format($collectionRate, 1) }}% tasa de cobro</p>
    </div>

    {{-- Pendiente --}}
    <div class="bg-white rounded-xl border border-outline-variant/60 p-5 shadow-card">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[12px] font-semibold uppercase tracking-wider text-on-surface-variant">Total pendiente</p>
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <i data-lucide="clock" class="w-4 h-4"></i>
            </div>
        </div>
        <p class="text-[26px] font-bold text-on-surface leading-7 tabular-nums">{{ $currencySymbol }}{{ number_format($pendingBalance, 2) }}</p>
        <p class="mt-2 text-[12px] text-on-surface-variant">{{ $pendingCount }} facturas activas</p>
    </div>

    {{-- Vencidas --}}
    <div class="bg-white rounded-xl border {{ $overdueCount > 0 ? 'border-rose-200' : 'border-outline-variant/60' }} p-5 shadow-card">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[12px] font-semibold uppercase tracking-wider text-on-surface-variant">Facturas vencidas</p>
            <div class="w-9 h-9 rounded-lg {{ $overdueCount > 0 ? 'bg-rose-50 text-rose-600' : 'bg-surface-mid text-on-surface-variant' }} flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
        </div>
        <p class="text-[26px] font-bold {{ $overdueCount > 0 ? 'text-rose-600' : 'text-on-surface' }} leading-7 tabular-nums">{{ $overdueCount }}</p>
        <p class="mt-2 text-[12px] {{ $overdueCount > 0 ? 'text-rose-700 font-semibold' : 'text-on-surface-variant' }}">
            {{ $overdueCount > 0 ? 'Requieren atención inmediata' : 'Todo al día' }}
        </p>
    </div>
</section>

{{-- Charts --}}
<section class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    <div class="bg-white rounded-xl border border-outline-variant/60 p-5 shadow-card lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-semibold text-on-surface">Facturación por mes</h2>
                <p class="text-[12px] text-on-surface-variant">Últimos 6 meses</p>
            </div>
            <div class="flex items-center gap-2 text-[12px] text-on-surface-variant">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-primary"></span>
                Total facturado
            </div>
        </div>
        <div class="h-[260px]">
            <canvas id="chartMonthly"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-outline-variant/60 p-5 shadow-card">
        <div class="mb-4">
            <h2 class="text-[16px] font-semibold text-on-surface">Facturas por estado</h2>
            <p class="text-[12px] text-on-surface-variant">Distribución total</p>
        </div>
        @if(count($statusChart) > 0)
            <div class="h-[180px] mb-4">
                <canvas id="chartStatus"></canvas>
            </div>
            <ul class="space-y-2">
                @foreach($statusChart as $row)
                    @php $s = $statusStyles[$row['status']] ?? $statusStyles['draft']; @endphp
                    <li class="flex items-center justify-between text-[13px]">
                        <span class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full" style="background-color: {{ $s['chart'] }}"></span>
                            <span class="text-on-surface">{{ $row['label'] }}</span>
                        </span>
                        <span class="font-semibold text-on-surface tabular-nums">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="h-[260px] flex items-center justify-center text-on-surface-variant text-[13px]">
                Aún no hay facturas registradas.
            </div>
        @endif
    </div>
</section>

{{-- Tabla facturas recientes --}}
<section class="bg-white rounded-xl border border-outline-variant/60 shadow-card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-[16px] font-semibold text-on-surface">Últimas facturas</h2>
            <p class="text-[12px] text-on-surface-variant">Actividad reciente</p>
        </div>
        <a href="{{ route('web.invoices.index') }}" class="text-[13px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
            Ver todas <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[13.5px]">
            <thead class="bg-surface-low">
                <tr class="text-left text-[11.5px] uppercase tracking-wider text-on-surface-variant">
                    <th class="px-5 py-3 font-semibold">Número</th>
                    <th class="px-5 py-3 font-semibold">Cliente</th>
                    <th class="px-5 py-3 font-semibold">Fecha</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Total</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
                @forelse($recentInvoices as $invoice)
                    @php $s = $statusStyles[$invoice->status] ?? $statusStyles['draft']; @endphp
                    <tr class="hover:bg-surface-low/60 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('web.invoices.show', $invoice) }}" class="font-semibold text-primary hover:underline">
                                {{ $invoice->invoice_number ?? 'BORRADOR' }}
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-on-surface">{{ $invoice->client_name }}</td>
                        <td class="px-5 py-3.5 text-on-surface-variant">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10.5px] font-bold uppercase tracking-wider {{ $s['bg'] }} {{ $s['text'] }}">
                                {{ InvoiceStatusLabel::label($invoice->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-on-surface">
                            {{ $invoice->currency_symbol }} {{ number_format((float) $invoice->total, 2) }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('web.invoices.show', $invoice) }}"
                               class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-primary hover:underline">
                                Ver <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-on-surface-variant">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p>Aún no hay facturas registradas.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const monthlyLabels = @json(array_column($monthlySeries, 'label'));
        const monthlyValues = @json(array_column($monthlySeries, 'value'));

        const monthlyCtx = document.getElementById('chartMonthly');
        if (monthlyCtx && window.Chart) {
            const gradient = monthlyCtx.getContext('2d').createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, 'rgba(29, 78, 216, 0.25)');
            gradient.addColorStop(1, 'rgba(29, 78, 216, 0)');

            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        data: monthlyValues,
                        borderColor: '#0037b0',
                        backgroundColor: gradient,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#0037b0',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.35,
                        fill: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: {
                        backgroundColor: '#1a1b23', titleColor: '#fff', bodyColor: '#fff',
                        padding: 10, displayColors: false,
                        callbacks: { label: (c) => '{{ $currencySymbol }}' + c.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }) },
                    }},
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#434655', font: { size: 12 } } },
                        y: { grid: { color: '#e2e1ed', drawBorder: false }, ticks: { color: '#434655', font: { size: 12 },
                            callback: (v) => v >= 1000 ? (v/1000).toFixed(0) + 'k' : v } },
                    },
                },
            });
        }

        @if(count($statusChart) > 0)
            const statusCtx = document.getElementById('chartStatus');
            if (statusCtx && window.Chart) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json(array_column($statusChart, 'label')),
                        datasets: [{
                            data: @json(array_column($statusChart, 'count')),
                            backgroundColor: @json(array_map(fn($r) => $statusStyles[$r['status']]['chart'] ?? '#94a3b8', $statusChart)),
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '68%',
                        plugins: { legend: { display: false }, tooltip: {
                            backgroundColor: '#1a1b23', titleColor: '#fff', bodyColor: '#fff', padding: 10,
                        }},
                    },
                });
            }
        @endif
    });
</script>
@endsection
