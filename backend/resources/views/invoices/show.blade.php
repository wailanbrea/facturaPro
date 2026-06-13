@extends('layouts.app')

@php($documentTitle = $invoice->document_type === 'quotation' ? 'PRESUPUESTO' : 'FACTURA')
@php($isQuotation = $invoice->document_type === 'quotation')
@php($canRegisterPayment = ! $isQuotation
    && ! in_array($invoice->status, ['draft', 'paid', 'cancelled'], true)
    && $invoice->invoice_number !== null
    && (float) $invoice->balance_due > 0.0)
@php($canConvertQuotation = $isQuotation
    && $invoice->converted_to_invoice_id === null
    && $invoice->invoice_number !== null
    && in_array($invoice->status, ['issued', 'accepted'], true))
@section('title', $invoice->invoice_number ?? ($documentTitle.' borrador'))
@section('subtitle', $invoice->client_name.' · '.$invoice->invoice_date?->toDateString())
@section('actions')
<a class="btn" href="{{ route('web.invoices.index') }}">Volver</a>
<a class="btn" href="{{ route('web.invoices.preview', $invoice) }}" target="_blank">Vista previa</a>
@if($invoice->status !== 'cancelled')
    @if($invoice->status === 'draft')
        <a class="btn" href="{{ route('web.invoices.edit', $invoice) }}">Editar</a>
        <form method="POST" action="{{ route('web.invoices.issue', $invoice) }}">@csrf<button class="btn primary" type="submit">Emitir</button></form>
    @endif
    @if($canConvertQuotation)
        <form method="POST" action="{{ route('web.invoices.convert', $invoice) }}" onsubmit="return confirm('Convertir este presupuesto en factura?');">@csrf<button class="btn primary" type="submit">Convertir a factura</button></form>
    @endif
    @if($canRegisterPayment)
        <form method="POST" action="{{ route('web.invoices.mark-paid', $invoice) }}">@csrf<button class="btn" type="submit">Marcar pagada</button></form>
    @endif
    <form method="POST" action="{{ route('web.invoices.cancel', $invoice) }}">@csrf<button class="btn danger" type="submit">Anular</button></form>
@endif
@if($invoice->converted_to_invoice_id)
    <a class="btn" href="{{ route('web.invoices.show', $invoice->converted_to_invoice_id) }}">Ver factura</a>
@endif
@if($invoice->source_quotation_id)
    <a class="btn" href="{{ route('web.invoices.show', $invoice->source_quotation_id) }}">Ver presupuesto</a>
@endif
@endsection

@section('content')
<div class="invoice-grid">
    <section class="card">
        <div class="actions" style="justify-content:space-between;margin-bottom:18px">
            <div>
                <h2 style="margin:0;color:var(--primary)">{{ $documentTitle }}</h2>
                <div class="muted">Estado: <span class="status {{ $invoice->status }}">{{ \App\Support\InvoiceStatusLabel::label($invoice->status) }}</span></div>
            </div>
            <div class="right">
                <strong>{{ $invoice->invoice_number ?? 'BORRADOR' }}</strong><br>
                <span class="muted">Vence: {{ $invoice->due_date?->toDateString() }}</span>
            </div>
        </div>
        <div class="grid" style="grid-template-columns:1fr 1fr;margin-bottom:20px">
            <div><strong>Facturar a</strong><br>{{ $invoice->client_name }}<br><span class="muted">{{ $invoice->client_tax_id }}<br>{{ $invoice->client_address }}</span></div>
            <div><strong>Emisor</strong><br>{{ $invoice->seller_name }}<br><span class="muted">{{ $invoice->seller_tax_id }}<br>{{ $invoice->seller_address }}</span></div>
        </div>
        <table class="table">
            <thead><tr><th>Descripcion</th><th class="right">Cant.</th><th class="right">Costo</th><th class="right">Imp.</th><th class="right">Total</th></tr></thead>
            <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td class="right">{{ $item->tax_name }}</td>
                    <td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:22px">
            <strong>Garantia / observaciones</strong>
            <p class="muted">{{ $invoice->warranty_text }}</p>
            <p>{{ $invoice->observations }}</p>
        </div>
    </section>
    <aside class="card">
        <h3>Totales</h3>
        <table class="table">
            <tr><td>Subtotal</td><td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            <tr><td>Impuestos</td><td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
            <tr><td><strong>Total</strong></td><td class="right"><strong>{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->total, 2) }}</strong></td></tr>
            @if(! $isQuotation)
                <tr><td>Recibido</td><td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->amount_received, 2) }}</td></tr>
                <tr><td>Pendiente</td><td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
            @else
                <tr><td>Total presupuesto</td><td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->total, 2) }}</td></tr>
            @endif
        </table>
        <h3 style="margin-top:22px">PDF</h3>
        @if($invoice->pdf_path)
            <a class="btn" href="{{ route('web.invoices.download-pdf', $invoice) }}">Descargar PDF</a>
        @else
            @if($invoice->invoice_number)
                <form method="POST" action="{{ route('web.invoices.generate-pdf', $invoice) }}">
                    @csrf
                    <button class="btn" type="submit">Generar PDF</button>
                </form>
            @else
                <button class="btn" disabled>Emite el documento para generar PDF</button>
            @endif
        @endif
    </aside>
</div>

@if($canRegisterPayment)
    <section class="card" style="margin-top:18px">
        <h3>Registrar pago</h3>
        <p class="muted">Balance pendiente: <strong id="pay-balance">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->balance_due, 2) }}</strong></p>
        @error('payment')
            <p style="color:#c00000">{{ $message }}</p>
        @enderror
        <form method="POST" action="{{ route('web.invoices.register-payment', $invoice) }}" class="form">
            @csrf
            <div class="fields">
                <div class="field">
                    <label>Monto a aplicar</label>
                    <input id="pay-amount" name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" value="{{ old('amount', $invoice->balance_due) }}" required>
                </div>
                <div class="field">
                    <label>Fecha</label>
                    <input name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Metodo</label>
                    <select name="method">
                        <option value="manual">Manual</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="field">
                    <label>Referencia</label>
                    <input name="reference" type="text" maxlength="128" value="{{ old('reference') }}">
                </div>
                <div class="field span-2">
                    <label>Notas</label>
                    <textarea name="notes" maxlength="500">{{ old('notes') }}</textarea>
                </div>
                <div class="field">
                    <label>Balance restante</label>
                    <input id="pay-remaining" type="text" value="{{ $invoice->currency_symbol }} 0.00" readonly>
                </div>
            </div>
            <div class="actions" style="margin-top:14px">
                <button class="btn primary" type="submit">Aplicar pago</button>
            </div>
        </form>
    </section>

    @if($invoice->payments->isNotEmpty())
        <section class="card" style="margin-top:18px">
            <h3>Pagos registrados</h3>
            <table class="table">
                <thead><tr><th>Fecha</th><th>Metodo</th><th>Referencia</th><th class="right">Monto</th></tr></thead>
                <tbody>
                @foreach($invoice->payments->sortByDesc('payment_date') as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('Y-m-d') }}</td>
                        <td>{{ $payment->method }}</td>
                        <td>{{ $payment->reference }}</td>
                        <td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <script>
        (function () {
            const balance = {{ (float) $invoice->balance_due }};
            const symbol = @json($invoice->currency_symbol);
            const amountInput = document.getElementById('pay-amount');
            const remainingInput = document.getElementById('pay-remaining');
            const format = (n) => symbol + ' ' + (Math.max(0, n)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const recalc = () => {
                const value = parseFloat(amountInput.value || '0');
                const remaining = balance - (isNaN(value) ? 0 : value);
                remainingInput.value = format(remaining);
            };
            amountInput.addEventListener('input', recalc);
            recalc();
        })();
    </script>
@endif
@endsection
