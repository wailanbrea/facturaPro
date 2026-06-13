@extends('layouts.app')

@section('title', 'Facturas y presupuestos')
@section('subtitle', 'Listado y seguimiento de documentos')
@section('actions')
<a class="btn primary" href="{{ route('web.invoices.create') }}">Nuevo documento</a>
@endsection

@section('content')
<form method="GET" class="actions" style="margin-bottom:16px">
    <input name="search" value="{{ request('search') }}" placeholder="Buscar factura o cliente" style="min-width:320px;border:1px solid var(--line);border-radius:5px;padding:10px">
    <select name="status" style="border:1px solid var(--line);border-radius:5px;padding:10px">
        <option value="">Todos</option>
        @foreach(['draft','issued','accepted','converted','partially_paid','paid','overdue','cancelled'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\InvoiceStatusLabel::label($status) }}</option>
        @endforeach
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>
<table class="table">
    <thead><tr><th>No.</th><th>Tipo</th><th>Cliente</th><th>Fecha</th><th>Vence</th><th>Estado</th><th class="right">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($invoices as $invoice)
        <tr>
            <td>{{ $invoice->invoice_number ?? 'BORRADOR' }}</td>
            <td>{{ $invoice->document_type === 'quotation' ? 'Presupuesto' : 'Factura' }}</td>
            <td>{{ $invoice->client_name }}</td>
            <td>{{ $invoice->invoice_date?->toDateString() }}</td>
            <td>{{ $invoice->due_date?->toDateString() }}</td>
            <td><span class="status {{ $invoice->status }}">{{ \App\Support\InvoiceStatusLabel::label($invoice->status) }}</span></td>
            <td class="right">{{ $invoice->currency_symbol }} {{ number_format((float) $invoice->total, 2) }}</td>
            <td class="right"><a class="btn" href="{{ route('web.invoices.show', $invoice) }}">Ver</a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Sin documentos.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="pagination">{{ $invoices->links() }}</div>
@endsection
