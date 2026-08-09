@php
    /**
     * Datos del cliente. A diferencia del emisor, aqui SI se muestran los
     * datos de contacto cuando existen.
     *
     * Correo y telefono salen del snapshot del documento; la ficha del cliente
     * solo es respaldo para documentos anteriores a la migracion
     * 2026_08_08. Editar un cliente no debe alterar un PDF ya emitido.
     *
     * @var \App\Models\Invoice $invoice
     * @var bool $showContact
     */
    $showContact = $showContact ?? true;
    $client = $invoice->client;
    $clientEmail = $invoice->client_email ?: $client?->email;
    $clientPhone = $invoice->client_phone ?: $client?->phone;
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
            <div class="line"><span class="label">Dirección:</span> {{ $invoice->client_address }}</div>
        @endif
        @if($invoice->client_city)
            <div class="line">{{ $invoice->client_city }}</div>
        @endif
        @if($showContact && $clientEmail)
            <div class="line"><span class="label">Correo:</span> {{ $clientEmail }}</div>
        @endif
        @if($showContact && $clientPhone)
            <div class="line"><span class="label">Contacto:</span> {{ $clientPhone }}</div>
        @endif
    </div>
</section>
