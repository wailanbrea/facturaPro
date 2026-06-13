@extends('layouts.app')

@inject('money', 'App\Services\CurrencyFormatterService')

@section('title', 'Verificar documento')
@section('subtitle', 'Comprueba si una factura o presupuesto es auténtico del sistema')

@section('actions')
<a class="btn" href="{{ route('web.invoices.index') }}">Volver</a>
@endsection

@section('content')
<div class="max-w-2xl space-y-6">
    <form method="GET" action="{{ route('web.invoices.verify') }}"
          class="rounded-2xl border border-outline-variant bg-surface p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant mb-1" for="number">Número de documento</label>
            <input id="number" name="number" value="{{ $number }}" required
                   class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2"
                   placeholder="FAC-000123">
        </div>
        <div>
            <label class="block text-sm font-medium text-on-surface-variant mb-1" for="code">Código de seguridad</label>
            <input id="code" name="code" value="{{ $code }}" required
                   class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-mono"
                   placeholder="A1B2-C3D4-E5F6-7890">
        </div>
        <button type="submit" class="btn btn-primary">Verificar</button>
    </form>

    @if ($result)
        @php($status = $result['status'])
        @php($invoice = $result['invoice'])

        @if ($status === 'authentic')
            <div class="rounded-2xl border-2 border-green-600 bg-green-50 p-5">
                <p class="text-lg font-bold text-green-800">✓ Documento auténtico</p>
                <p class="text-sm text-green-900 mt-1">
                    Emitido por el sistema y sin alteraciones. Compare estos datos con el ejemplar impreso:
                </p>
                <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                    <dt class="text-on-surface-variant">Número</dt><dd class="font-semibold">{{ $invoice->invoice_number }}</dd>
                    <dt class="text-on-surface-variant">Emisor</dt><dd>{{ $invoice->seller_name }} · {{ $invoice->seller_tax_id }}</dd>
                    <dt class="text-on-surface-variant">Cliente</dt><dd>{{ $invoice->client_name }} · {{ $invoice->client_tax_id }}</dd>
                    <dt class="text-on-surface-variant">Fecha</dt><dd>{{ $invoice->invoice_date?->toDateString() }}</dd>
                    <dt class="text-on-surface-variant">Total</dt>
                    <dd class="font-bold">{{ $money->format($invoice->total, [
                        'symbol' => $invoice->currency_symbol,
                        'decimal_separator' => $invoice->currency_decimal_separator,
                        'thousand_separator' => $invoice->currency_thousand_separator,
                        'decimal_places' => $invoice->currency_decimal_places,
                        'symbol_position' => $invoice->currency_symbol_position,
                    ]) }}</dd>
                </dl>
            </div>
        @elseif ($status === 'altered')
            <div class="rounded-2xl border-2 border-red-600 bg-red-50 p-5">
                <p class="text-lg font-bold text-red-800">✗ Documento alterado</p>
                <p class="text-sm text-red-900 mt-1">
                    Existe un registro con ese número y código, pero sus datos almacenados no coinciden con la
                    firma original. No confíe en este documento; repórtelo de inmediato.
                </p>
            </div>
        @elseif ($status === 'code_mismatch')
            <div class="rounded-2xl border-2 border-red-600 bg-red-50 p-5">
                <p class="text-lg font-bold text-red-800">✗ No auténtico</p>
                <p class="text-sm text-red-900 mt-1">
                    El código de seguridad no corresponde al número indicado. El documento es una copia no
                    auténtica o los datos fueron manipulados.
                </p>
            </div>
        @else
            <div class="rounded-2xl border-2 border-red-600 bg-red-50 p-5">
                <p class="text-lg font-bold text-red-800">✗ No encontrado</p>
                <p class="text-sm text-red-900 mt-1">
                    No existe ningún documento emitido con ese número. Es una copia no auténtica.
                </p>
            </div>
        @endif
    @endif
</div>
@endsection
