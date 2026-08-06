@php
    /**
     * Datos del cliente. A diferencia del emisor, aqui SI se muestran los
     * datos de contacto cuando existen.
     *
     * @var \App\Models\Invoice $invoice
     * @var bool $showContact
     */
    $showContact = $showContact ?? true;
    $client = $invoice->client;
@endphp
<section class="card-box">
    <div class="card-head">
        <x-pdf-icon name="user" :size="9" />
        Datos del cliente
    </div>
    <div class="card-body">
        <div class="name">{{ $invoice->client_name }}</div>
        @if($invoice->client_tax_id)
            <div class="line"><span class="label">NIF / CIF:</span> {{ $invoice->client_tax_id }}</div>
        @endif
        @if($invoice->client_address)
            <div class="line">{{ $invoice->client_address }}</div>
        @endif
        @if($invoice->client_city)
            <div class="line">{{ $invoice->client_city }}</div>
        @endif
        @if($showContact && $client?->email)
            <div class="line">{{ $client->email }}</div>
        @endif
        @if($showContact && $client?->phone)
            <div class="line">{{ $client->phone }}</div>
        @endif
    </div>
</section>
