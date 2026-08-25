@inject('money', 'App\Services\CurrencyFormatterService')
@php
    /** @var \App\Models\Invoice $invoice */
    $ctx = \App\Support\PdfDocumentContext::for($invoice, firstPageRows: 13, nextPageRows: 24);
    $currency = $ctx->currency;
    $intervention = $invoice->intervention;

    $statusLabel = \App\Support\InvoiceStatusLabel::label($invoice->status);
    $isPaid = strtolower((string) $invoice->status) === 'paid';

    $legalBlocks = [
        ['icon' => 'shield-check', 'tone' => 'navy', 'title' => 'GARANTÍA LEGAL', 'text' => 'Garantía de '.\App\Models\Warranty::durationLabelFor($invoice->warranty?->duration_months).' en mano de obra y materiales suministrados, conforme a la normativa vigente.'],
        ['icon' => 'x-circle', 'tone' => 'accent', 'title' => 'EXCLUSIONES', 'text' => 'No cubre daños por mal uso, manipulación de terceros, falta de mantenimiento, sobretensiones o causas externas.'],
        ['icon' => 'scale', 'tone' => 'navy', 'title' => 'LIMITACIONES', 'text' => 'Responsabilidad limitada al valor de la intervención realizada.'],
        ['icon' => 'search', 'tone' => 'accent', 'title' => 'DAÑOS OCULTOS', 'text' => 'Averías no visibles en el momento de la intervención.'],
        ['icon' => 'settings', 'tone' => 'navy', 'title' => 'EQUIPOS ANTIGUOS', 'text' => 'En equipos con más de 10 años no se garantiza la disponibilidad de repuestos.'],
        ['icon' => 'file-text', 'tone' => 'accent', 'title' => 'PRESUPUESTOS', 'text' => 'Validez de 30 días naturales desde la fecha de emisión.'],
        ['icon' => 'lock', 'tone' => 'navy', 'title' => 'PROTECCIÓN DE DATOS', 'text' => 'Tratamiento de datos conforme al RGPD (UE 2016/679).'],
        ['icon' => 'euro', 'tone' => 'accent', 'title' => 'FACTURACIÓN Y PAGO', 'text' => 'Pago en la forma y plazo indicados en la factura.'],
        ['icon' => 'check-circle', 'tone' => 'navy', 'title' => 'ACEPTACIÓN', 'text' => 'La aceptación implica conformidad con las condiciones del servicio.'],
        ['icon' => 'landmark', 'tone' => 'accent', 'title' => 'JURISDICCIÓN', 'text' => 'Para cualquier controversia, serán competentes los juzgados de '.($invoice->seller_city ?: 'Barcelona').'.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>FACTURA {{ $invoice->invoice_number ?? 'BORRADOR' }}</title>
    @include('pdf.partials.styles')
</head>
<body class="doc doc--invoice">

@foreach($ctx->pages as $pageIndex => $rows)
    @php
        $pageNo = $pageIndex + 1;
        $isFirst = $pageIndex === 0;
        $isLastItemsSheet = $pageIndex === count($ctx->pages) - 1;
    @endphp
    <section class="sheet">
        @include('pdf.partials.watermark', ['watermark' => $ctx->watermark])
        @if($isFirst)
        @include('pdf.partials.doc-heading', [
            'logoSrc' => $ctx->logoSrc,
            'sellerInitial' => $ctx->sellerInitial,
            'title' => 'Factura / Informe<br>de intervención técnica',
            'titleTwoLine' => true,
            'numberLabel' => 'Nº Factura',
            'numberValue' => $invoice->invoice_number ?? 'BORRADOR',
            'qrSrc' => $ctx->qrSrc,
            'qrTitle' => 'Verifica tu factura',
            'qrText' => 'Escanea el código QR para verificar la autenticidad de este documento.',
            'pageNo' => $pageNo,
            'totalPages' => $ctx->totalPages,
        ])
        @else
            <div class="page-flag">P&Aacute;GINA {{ $pageNo }} DE {{ $ctx->totalPages }}</div>
        @endif


        @if($isFirst)
            <div class="cards-row">
                @include('pdf.partials.issuer-card', ['invoice' => $invoice])
                @include('pdf.partials.client-card', ['invoice' => $invoice, 'showContact' => true])

                <section class="card-box">
                    <div class="card-head">
                        <x-pdf-icon name="wrench" :size="9" />
                        Equipo objeto de la intervención
                    </div>
                    <div class="card-body">
                        @if($intervention?->hasEquipmentData())
                            <dl style="margin:0">
                                @if($intervention->equipment_type)
                                    <div class="kv"><dt>Equipo:</dt><dd>{{ $intervention->equipment_type }}</dd></div>
                                @endif
                                {{-- Fabricante siempre visible: forma parte de la ficha del
                                     equipo aunque todavia no se haya rellenado. --}}
                                <div class="kv"><dt>Fabricante:</dt><dd>{{ $intervention->equipment_brand ?: '—' }}</dd></div>
                                @if($intervention->equipment_model)
                                    <div class="kv"><dt>Modelo:</dt><dd>{{ $intervention->equipment_model }}</dd></div>
                                @endif
                                @if($intervention->equipment_serial)
                                    <div class="kv"><dt>Nº de serie:</dt><dd>{{ $intervention->equipment_serial }}</dd></div>
                                @endif
                                @if($intervention->equipment_location)
                                    <div class="kv"><dt>Ubicación:</dt><dd>{{ $intervention->equipment_location }}</dd></div>
                                @endif
                                @if($intervention->units_indoor !== null || $intervention->units_outdoor !== null)
                                    <div class="kv"><dt>Nº de unidades:</dt><dd>
                                        @if($intervention->units_indoor !== null)Interior: {{ $intervention->units_indoor }}@endif
                                        @if($intervention->units_indoor !== null && $intervention->units_outdoor !== null) &middot; @endif
                                        @if($intervention->units_outdoor !== null)Exterior: {{ $intervention->units_outdoor }}@endif
                                    </dd></div>
                                @endif
                            </dl>
                        @else
                            <div class="note-text muted">Sin datos de equipo registrados para esta intervención.</div>
                        @endif
                    </div>
                </section>

                <section class="card-box">
                    <div class="card-head">
                        <x-pdf-icon name="calendar" :size="9" />
                        Datos de la factura
                    </div>
                    <div class="card-body">
                        @php
                            $effectiveDueDate = $invoice->due_date ?: ($invoice->invoice_date ? $invoice->invoice_date->copy()->addDays(30) : null);
                            if ($invoice->invoice_date && $effectiveDueDate && $effectiveDueDate->lessThanOrEqualTo($invoice->invoice_date)) {
                                $effectiveDueDate = $invoice->invoice_date->copy()->addDays(30);
                            }
                        @endphp
                        <dl style="margin:0">
                            <div class="kv"><dt>Fecha de emisión:</dt><dd>{{ $invoice->invoice_date?->format('d/m/Y') ?: '-' }}</dd></div>
                            <div class="kv"><dt>Fecha de vencimiento:</dt><dd>{{ $effectiveDueDate?->format('d/m/Y') ?: '-' }}</dd></div>
                            <div class="kv"><dt>Término de pago:</dt><dd>{{ $invoice->paymentTerm?->name ?: 'Al contado' }}</dd></div>
                            <div class="kv"><dt>Forma de pago:</dt><dd>{{ $invoice->paymentMethodLabel() }}</dd></div>
                            {{-- El estado va como texto simple, nunca como bloque destacado. --}}
                            <div class="kv"><dt>Estado de la factura:</dt><dd class="{{ $isPaid ? 'paid' : 'strong' }}">{{ mb_strtoupper($statusLabel) }}</dd></div>
                            {{-- El tecnico asignado se guarda pero no se imprime todavia. --}}
                        </dl>
                    </div>
                </section>
            </div>
        @endif

        <div class="work-row {{ $isFirst ? 'with-two-asides' : 'full' }}">
            @include('pdf.partials.items-table', [
                'rows' => $rows,
                'startIndex' => $ctx->startIndexFor($pageIndex),
                'caption' => 'Actuaciones técnicas realizadas',
                'descriptionHeading' => 'Descripción de la actuación',
                'money' => $money,
                'currency' => $currency,
            ])

            @if($isFirst)
                <div>
                    <div class="aside-box">
                        <div class="note-title"><x-pdf-icon name="search" :size="9" /> Diagnóstico técnico</div>
                        <div class="note-text muted">{{ $intervention?->diagnostic_summary ?: 'Sin diagnóstico registrado.' }}</div>
                    </div>
                    <div class="aside-box">
                        <div class="note-title"><x-pdf-icon name="check-circle" :size="9" /> Conclusiones técnicas</div>
                        <div class="note-text muted">{{ $intervention?->technical_conclusions ?: 'Sin conclusiones registradas.' }}</div>
                    </div>
                </div>

                <div>
                    <section class="card-box">
                        <div class="card-head">
                            <x-pdf-icon name="euro" :size="9" />
                            Resumen económico
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
                                @if((float) $invoice->amount_received > 0)
                                    <div class="kv"><dt>Importe recibido:</dt><dd>{{ $money->format($invoice->amount_received, $currency) }}</dd></div>
                                    @if((float) $invoice->balance_due > 0.0001)
                                        <div class="kv"><dt>Balance pendiente:</dt><dd class="strong">{{ $money->format($invoice->balance_due, $currency) }}</dd></div>
                                    @endif
                                @endif
                            </dl>
                            <div class="totals-highlight single">
                                <div class="hl">
                                    <div class="hl-label">Importe total</div>
                                    <div class="hl-value">{{ $money->format($invoice->total, $currency) }}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            @endif
        </div>

        @if($isLastItemsSheet && !$ctx->hasDedicatedBottomSheet)
            {{-- La garantia no se repite aqui: ya figura en las condiciones de la
                 ultima pagina. --}}
            <div class="bottom-row cols-3">
                <div class="note-box">
                    <span class="note-ico"><x-pdf-icon name="pen-line" :size="12" /></span>
                    <div style="flex:1">
                        <div class="note-title">Aceptación de la intervención</div>
                        <div class="note-text muted">{{ $invoice->conformity_text ?: \App\Models\Invoice::DEFAULT_INTERVENTION_ACCEPTANCE }}</div>
                    </div>
                </div>

                @include('pdf.partials.bank-transfer', ['invoice' => $invoice])

                <div class="note-box">
                    <span class="note-ico"><x-pdf-icon name="file-text" :size="12" /></span>
                    <div>
                        <div class="note-title">Condiciones</div>
                        <div class="note-text muted">La presente factura se complementa con las Condiciones Generales de Prestación del Servicio incluidas en la página {{ $ctx->totalPages }}, que forman parte integrante del presente documento.</div>
                    </div>
                </div>
            </div>
            @if(!$ctx->hasDedicatedLegalSheet)
                @include('pdf.partials.invoice-legal-content', ['ctx' => $ctx, 'legalBlocks' => $legalBlocks])
            @endif
        @endif
    </section>
@endforeach

@if($ctx->hasDedicatedBottomSheet)
<section class="sheet bottom-sheet">
    <div class="page-flag">PÁGINA {{ count($ctx->pages) + 1 }} DE {{ $ctx->totalPages }}</div>
    <div class="bottom-row cols-3">
        <div class="note-box">
            <span class="note-ico"><x-pdf-icon name="pen-line" :size="12" /></span>
            <div style="flex:1">
                <div class="note-title">Aceptación de la intervención</div>
                <div class="note-text muted">{{ $invoice->conformity_text ?: \App\Models\Invoice::DEFAULT_INTERVENTION_ACCEPTANCE }}</div>
            </div>
        </div>

        @include('pdf.partials.bank-transfer', ['invoice' => $invoice])

        <div class="note-box">
            <span class="note-ico"><x-pdf-icon name="file-text" :size="12" /></span>
            <div>
                <div class="note-title">Condiciones</div>
                <div class="note-text muted">La presente factura se complementa con las Condiciones Generales de Prestación del Servicio incluidas en la página {{ $ctx->totalPages }}, que forman parte integrante del presente documento.</div>
            </div>
        </div>
    </div>
</section>
@endif
@if($ctx->hasDedicatedLegalSheet)
{{-- Las condiciones se emiten fuera del bucle: siempre son la última hoja. --}}
<section class="sheet">
    <div class="page-flag">PÁGINA {{ $ctx->totalPages }} DE {{ $ctx->totalPages }}</div>
    <div class="legal-band">CONDICIONES GENERALES DE PRESTACIÓN DEL SERVICIO</div>

    @include('pdf.partials.legal-grid', ['blocks' => $legalBlocks])

    @if($ctx->verificationCode)
        <div class="muted" style="margin-top:4mm">
            Documento emitido y autenticado por el sistema. Código de seguridad:
            <span class="verify-code">{{ $ctx->verificationCode }}</span>
        </div>
    @endif

    @include('pdf.partials.eco-notice')
</section>
@endif

</body>
</html>
