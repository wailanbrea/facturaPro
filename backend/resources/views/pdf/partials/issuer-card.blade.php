@php
    /**
     * Datos fiscales del emisor.
     *
     * Deliberadamente SIN telefono, email ni web: son zona prohibida en la
     * tarjeta fiscal aunque la maqueta de referencia los insinue.
     *
     * @var \App\Models\Invoice $invoice
     */
@endphp
<section class="card-box">
    <div class="card-head">
        <x-pdf-icon name="building" :size="9" />
        Datos fiscales del emisor
    </div>
    <div class="card-body">
        <div class="name">{{ $invoice->seller_name ?: 'FacturaPro' }}</div>
        @if($invoice->seller_tax_id)
            <div class="line"><span class="label">NIF:</span> {{ $invoice->seller_tax_id }}</div>
        @endif
        @if($invoice->seller_address)
            <div class="line">{{ $invoice->seller_address }}</div>
        @endif
        @if($invoice->seller_city)
            <div class="line">{{ $invoice->seller_city }}</div>
        @endif
    </div>
</section>
