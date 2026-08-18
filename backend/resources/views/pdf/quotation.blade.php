@inject('money', 'App\Services\CurrencyFormatterService')
@php
    /** @var \App\Models\Invoice $invoice */
    $ctx = \App\Support\PdfDocumentContext::for($invoice, firstPageRows: 11, nextPageRows: 24);
    $currency = $ctx->currency;
    $intervention = $invoice->intervention;

    // La validez del presupuesto se guarda en due_date, que el backend fuerza
    // a fecha de emision + 30 dias.
    $validUntil = $invoice->due_date?->format('d/m/Y') ?: '-';
    $includedItems = $intervention?->includedItemsList() ?? collect();

    $legalBlocks = [
        ['icon' => 'shield-check', 'tone' => 'navy', 'title' => 'Garantía', 'text' => 'Garantía de '.\App\Models\Warranty::durationLabelFor($invoice->warranty?->duration_months).' en mano de obra y materiales suministrados, conforme a la normativa vigente.'],
        ['icon' => 'x-circle', 'tone' => 'accent', 'title' => 'Validez', 'text' => 'Este presupuesto es válido hasta la fecha indicada. Pasado este plazo, podrá estar sujeto a cambios.'],
        ['icon' => 'scale', 'tone' => 'navy', 'title' => 'Condiciones', 'text' => 'Sujeto a nuestras Condiciones Generales de Prestación del Servicio.'],
        ['icon' => 'clock', 'tone' => 'accent', 'title' => 'Plazos de ejecución', 'text' => 'Los trabajos se realizarán en la fecha acordada, según disponibilidad de material y agenda.'],
        ['icon' => 'settings', 'tone' => 'navy', 'title' => 'Materiales', 'text' => 'Utilizamos repuestos originales o de primeras marcas equivalentes.'],
        ['icon' => 'file-text', 'tone' => 'accent', 'title' => 'Modificaciones', 'text' => 'Cualquier cambio en el alcance deberá ser aprobado por ambas partes antes de ejecutarse.'],
        ['icon' => 'lock', 'tone' => 'navy', 'title' => 'Protección de datos', 'text' => 'Tratamiento de datos conforme al RGPD (UE 2016/679).'],
        ['icon' => 'euro', 'tone' => 'accent', 'title' => 'Cancelación', 'text' => 'Cancelaciones con menos de 24 h de antelación podrán tener coste de desplazamiento.'],
        ['icon' => 'check-circle', 'tone' => 'navy', 'title' => 'Aceptación', 'text' => 'La aceptación implica conformidad con las condiciones del servicio.'],
        ['icon' => 'landmark', 'tone' => 'navy', 'title' => 'Jurisdicción', 'text' => 'Para cualquier controversia, serán competentes los juzgados de '.($invoice->seller_city ?: 'la sede del emisor').'.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>PRESUPUESTO {{ $invoice->invoice_number ?? 'BORRADOR' }}</title>
    @include('pdf.partials.styles')
</head>
<body class="doc doc--quotation">

@foreach($ctx->pages as $pageIndex => $rows)
    @php
        $pageNo = $pageIndex + 1;
        $isFirst = $pageIndex === 0;
        $isLastItemsSheet = $pageIndex === count($ctx->pages) - 1;
    @endphp
    <section class="sheet">
        @include('pdf.partials.watermark', ['watermark' => $ctx->watermark])
        @include('pdf.partials.doc-heading', [
            'logoSrc' => $ctx->logoSrc,
            'sellerInitial' => $ctx->sellerInitial,
            'title' => 'Presupuesto',
            'numberLabel' => 'Nº Presupuesto',
            'numberValue' => $invoice->invoice_number ?? 'BORRADOR',
            'qrSrc' => $ctx->qrSrc,
            'qrTitle' => 'Escanea para verificar autenticidad',
            'qrText' => 'de este documento.',
            'pageNo' => $pageNo,
            'totalPages' => $ctx->totalPages,
        ])

        @if($isFirst)
            <div class="cards-row">
                @include('pdf.partials.issuer-card', ['invoice' => $invoice])
                @include('pdf.partials.client-card', ['invoice' => $invoice, 'showContact' => true])

                <section class="card-box">
                    <div class="card-head">
                        <x-pdf-icon name="clipboard-list" :size="9" />
                        Detalles del presupuesto
                    </div>
                    <div class="card-body">
                        <dl style="margin:0">
                            <div class="kv"><dt>Fecha de emisión:</dt><dd>{{ $invoice->invoice_date?->format('d/m/Y') ?: '-' }}</dd></div>
                            <div class="kv"><dt>Fecha de validez:</dt><dd>{{ $validUntil }}</dd></div>
                            <div class="kv"><dt>Término de pago:</dt><dd>{{ $invoice->paymentTerm?->name ?: 'Al contado' }}</dd></div>
                            <div class="kv"><dt>Forma de pago:</dt><dd></dd></div>
                            {{-- Tecnico asignado y referencia/obra se guardan pero no se
                                 imprimen todavia. --}}
                            @if($invoice->service_location)
                                <div class="kv"><dt>Lugar de intervención:</dt><dd>{{ $invoice->service_location }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </section>

                <section class="card-box">
                    <div class="card-head">
                        <x-pdf-icon name="euro" :size="9" />
                        Resumen del presupuesto
                    </div>
                    <div class="card-body">
                        <dl style="margin:0">
                            <div class="kv"><dt>Subtotal:</dt><dd>{{ $money->format($invoice->subtotal, $currency) }}</dd></div>
                            @if((float) $invoice->discount_total > 0)
                                <div class="kv">
                                    <dt>Descuento @if((float) $invoice->discount_percent > 0)({{ rtrim(rtrim(number_format((float) $invoice->discount_percent, 2, ',', '.'), '0'), ',') }}%)@endif:</dt>
                                    <dd>-{{ $money->format($invoice->discount_total, $currency) }}</dd>
                                </div>
                            @endif
                            @if((float) $invoice->travel_amount > 0)
                                <div class="kv"><dt>Desplazamiento:</dt><dd>{{ $money->format($invoice->travel_amount, $currency) }}</dd></div>
                            @endif
                            <div class="kv"><dt>Base imponible:</dt><dd>{{ $money->format($invoice->taxableBaseOrSubtotal(), $currency) }}</dd></div>
                            <div class="kv"><dt>{{ $ctx->taxLabel }}:</dt><dd>{{ $money->format($invoice->tax_total, $currency) }}</dd></div>
                        </dl>
                        <div class="totals-highlight">
                            <div class="hl">
                                <div class="hl-label">Total presupuestado</div>
                                <div class="hl-value">{{ $money->format($invoice->total, $currency) }}</div>
                            </div>
                            <div class="hl green">
                                <div class="hl-label">Válido hasta</div>
                                <div class="hl-value">{{ $validUntil }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @endif

        <div class="work-row {{ $isFirst ? 'with-aside' : 'full' }}">
            @include('pdf.partials.items-table', [
                'rows' => $rows,
                'startIndex' => $ctx->startIndexFor($pageIndex),
                'caption' => 'Detalle de los trabajos y suministros',
                'descriptionHeading' => 'Descripción',
                'money' => $money,
                'currency' => $currency,
            ])

            @if($isFirst)
                <div>
                    @php
                        $serviceScope = $intervention?->service_scope ?: \App\Models\Invoice::DEFAULT_QUOTATION_SERVICE_SCOPE;
                    @endphp
                    @if(filled($serviceScope))
                        <div class="aside-box">
                            <div class="note-title" style="display:flex;align-items:center;gap:1.4mm">
                                <x-pdf-icon name="search" :size="8" />
                                <span>ALCANCE DEL SERVICIO</span>
                            </div>
                            <div class="note-text" style="font-size:7pt;line-height:1.35;color:var(--ink);margin-top:1.2mm">
                                {{ $serviceScope }}
                            </div>
                        </div>
                    @endif

                    @if($includedItems->isNotEmpty())
                        <div class="aside-box">
                            <div class="note-title">Incluye</div>
                            <ul class="check-list">
                                @foreach($includedItems as $line)
                                    <li><x-pdf-icon name="check" :size="8" /> <span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if($isLastItemsSheet)
            <div class="bottom-row cols-3">
                <div class="note-box">
                    <span class="note-ico"><x-pdf-icon name="file-text" :size="12" /></span>
                    <div>
                        <div class="note-title">Observaciones</div>
                        <div class="note-text muted">{{ $invoice->observations ?: \App\Models\Invoice::DEFAULT_QUOTATION_OBSERVATIONS }}</div>
                    </div>
                </div>

                @include('pdf.partials.bank-transfer', ['invoice' => $invoice])

                <div class="note-box">
                    <span class="note-ico"><x-pdf-icon name="pen-line" :size="12" /></span>
                    <div style="flex:1">
                        <div class="note-title">Aceptación del presupuesto</div>
                        <div class="note-text muted">Para aceptar el presente presupuesto, comuníquese con la persona que le proporcionó este documento y confirme su aceptación antes de la fecha de vencimiento indicada. La aceptación dentro de este plazo permitirá gestionar oportunamente la reserva y programación del servicio.</div>
                    </div>
                </div>
            </div>
        @endif
    </section>
@endforeach

{{-- Las condiciones se emiten fuera del bucle: siempre son la ultima hoja. --}}
<section class="sheet">
    <div class="page-flag">PÁGINA {{ $ctx->totalPages }} DE {{ $ctx->totalPages }}</div>
    <div class="legal-band">CONDICIONES DEL PRESUPUESTO</div>

    @include('pdf.partials.legal-grid', ['blocks' => $legalBlocks])

    @if($ctx->verificationCode)
        <div class="muted" style="margin-top:4mm">
            Documento emitido y autenticado por el sistema. Código de seguridad:
            <span class="verify-code">{{ $ctx->verificationCode }}</span>
        </div>
    @endif

    @include('pdf.partials.eco-notice')
</section>

</body>
</html>
