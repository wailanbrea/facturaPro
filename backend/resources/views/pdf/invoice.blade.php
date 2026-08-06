@inject('money', 'App\Services\CurrencyFormatterService')
@inject('qr', 'App\Services\QrCodeService')
@inject('signature', 'App\Services\InvoiceSignatureService')
@php
    $currency = [
        'symbol' => $invoice->currency_symbol,
        'decimal_separator' => $invoice->currency_decimal_separator,
        'thousand_separator' => $invoice->currency_thousand_separator,
        'decimal_places' => $invoice->currency_decimal_places,
        'symbol_position' => $invoice->currency_symbol_position,
    ];

    $isQuotation = $invoice->document_type === 'quotation';
    $documentTitle = $isQuotation ? 'PRESUPUESTO' : 'FACTURA';
    $documentSubtitle = $isQuotation ? '' : 'DE INTERVENCION TECNICA';
    $numberLabel = $isQuotation ? 'N.° PRESUPUESTO' : 'FACTURA N.°';
    $totalLabel = $isQuotation ? 'TOTAL PRESUPUESTO' : 'TOTAL FACTURA';
    $payTotalLabel = $isQuotation ? 'TOTAL PRESUPUESTO' : 'TOTAL A PAGAR';
    $quotationValidUntil = $isQuotation
        ? $invoice->invoice_date?->copy()->addDays(30)
        : $invoice->due_date;

    $warrantyText = $invoice->warranty?->full_text
        ?: $invoice->warranty_text
        ?: 'GARANTIA SEGUN CONDICIONES DEL FABRICANTE';
    $legalText = $invoice->legal_text
        ?: $invoice->conformity_text
        ?: 'La firma, aceptacion digital o pago del servicio confirma la conformidad del trabajo realizado. La garantia cubre exclusivamente la reparacion realizada y las piezas sustituidas, excluyendo averias derivadas de manipulacion externa, mal uso o desgaste natural.';

    $logoSrc = null;
    $logoPath = $invoice->logo_path ?? $invoice->fiscalProfile?->logo_path;
    if ($logoPath) {
        $absoluteLogoPath = storage_path('app/public/'.$logoPath);
        if (is_file($absoluteLogoPath)) {
            $mime = function_exists('mime_content_type') ? mime_content_type($absoluteLogoPath) : null;
            $mime = $mime ?: 'image/png';
            $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absoluteLogoPath));
        }
    }

    $sellerInitial = strtoupper(substr((string) ($invoice->seller_name ?: 'F'), 0, 1));
    $sellerPhone = $invoice->fiscalProfile?->phone;
    $sellerEmail = $invoice->fiscalProfile?->email;
    $sellerAddress = trim(collect([$invoice->seller_address, $invoice->seller_city])->filter()->implode(', '));
    $sellerTaxId = $invoice->seller_tax_id;
    $sellerNif = $invoice->fiscalProfile?->tax_id ?? $invoice->seller_tax_id;
    $sellerNifLabel = $invoice->fiscalProfile?->tax_label ?? 'NIF';

    $client = $invoice->client;
    $clientPhone = $client?->phone;
    $clientEmail = $client?->email;
    $clientName = $invoice->client_name;
    $clientTaxId = $invoice->client_tax_id;
    $clientAddress = $invoice->client_address;
    $clientCity = $invoice->client_city;

    $lineCount = $invoice->items->count();
    $fillerRows = max(0, min(4, 4 - $lineCount));

    $status = strtolower((string) $invoice->status);
    $watermark = match (true) {
        in_array($status, ['cancelled', 'anulada'], true) => 'ANULADA',
        in_array($status, ['draft', 'borrador'], true) => 'BORRADOR',
        $isQuotation && $status === 'converted' => 'CONVERTIDO',
        $isQuotation && in_array($status, ['accepted', 'aceptado'], true) => 'ACEPTADO',
        $isQuotation => 'PRESUPUESTO',
        in_array($status, ['paid', 'pagada'], true) => 'COBRAT',
        default => null,
    };

    $paymentTermName = $invoice->paymentTerm?->name ?: ' ';
    $dueText = $invoice->due_date?->format('d/m/Y') ?: ($paymentTermName !== ' ' ? $paymentTermName : 'CONTADO');

    $isSigned = filled($invoice->verification_code) && filled($invoice->verification_hash);
    $verificationCode = $invoice->verification_code;
    $verificationUrl = $isSigned ? $signature->verificationUrl($invoice) : null;
    $verificationQr = $verificationUrl ? $qr->svgDataUri($verificationUrl) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $invoice->invoice_number ?? 'BORRADOR' }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111827;
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 8px;
            line-height: 1.15;
        }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .invoice-page {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            padding: 3mm 5mm 2mm 5mm;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .document {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
            width: 100%;
        }
        td, th {
            border: 0.5px solid #AAB8C7;
            padding: 1.2mm 1.5mm;
            vertical-align: middle;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { color: #374151; }
        .nowrap { white-space: nowrap; }
        .wrap { overflow-wrap: anywhere; word-break: normal; }

        /* ===== HEADER ===== */
        .header-top {
            display: grid;
            grid-template-columns: 25mm 1fr auto;
            gap: 4mm;
            align-items: start;
        }
        .logo-box {
            width: 25mm;
            height: 22mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #AAB8C7;
            border-radius: 2px;
        }
        .logo-box img {
            max-width: 25mm;
            max-height: 22mm;
            object-fit: contain;
        }
        .logo-initial {
            width: 22mm;
            height: 22mm;
            border: 1.5px solid #062A55;
            color: #062A55;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
        }
        .service-icons {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1mm;
            padding-top: 1.5mm;
        }
        .service-icon {
            text-align: center;
            font-size: 5.5px;
            color: #062A55;
            font-weight: 700;
            line-height: 1.1;
        }
        .service-icon .icon-symbol {
            display: inline-block;
            width: 7mm;
            height: 7mm;
            border: 0.8px solid #062A55;
            border-radius: 50%;
            line-height: 7mm;
            font-size: 10px;
            font-weight: 800;
            color: #062A55;
            margin-bottom: 0.3mm;
        }
        .doc-title-area {
            text-align: right;
        }
        .doc-title {
            color: #062A55;
            font-size: 24px;
            line-height: .9;
            font-weight: 800;
            text-transform: uppercase;
        }
        .doc-subtitle {
            color: #062A55;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1mm;
        }

        /* ===== TOP INFO ROW (4 columns, landscape) ===== */
        .top-info-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 2mm;
        }
        .info-box {
            border: 0.5px solid #AAB8C7;
            border-radius: 2px;
            overflow: hidden;
        }
        .info-box-header {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            font-size: 7px;
            text-transform: uppercase;
            padding: 1mm 1.5mm;
            text-align: center;
        }
        .info-box-body {
            padding: 1.5mm;
            font-size: 7.2px;
            line-height: 1.25;
        }
        .info-row {
            display: grid;
            grid-template-columns: 18mm minmax(0, 1fr);
            row-gap: 0.5mm;
            column-gap: 1mm;
            margin-bottom: 0.5mm;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-label {
            font-weight: 800;
            color: #111827;
            font-size: 6.5px;
        }
        .info-value {
            font-size: 7.2px;
        }

        /* ===== ITEMS TABLE ===== */
        .items-section {
            display: grid;
            grid-template-columns: 1fr 75mm;
            gap: 2.5mm;
            align-items: start;
        }
        .items-table {
            table-layout: fixed;
            page-break-inside: auto;
        }
        .items-table th {
            background: #062A55;
            color: #fff;
            height: 6mm;
            font-size: 7px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .items-table td {
            height: 5mm;
            font-size: 6.5px;
        }
        .items-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .items-table .concept { width: 50%; }
        .items-table .qty { width: 10%; text-align: center; }
        .items-table .unit { width: 20%; text-align: right; }
        .items-table .amount { width: 20%; text-align: right; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 1mm;
            color: #062A55;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 1mm;
        }
        .icon {
            color: #062A55;
            font-weight: 800;
            font-size: 8px;
            line-height: 1;
            text-align: center;
        }
        .scope-panel {
            border: 0.5px solid #AAB8C7;
            border-radius: 2px;
            padding: 1mm;
            font-size: 6.5px;
            line-height: 1.2;
            min-height: 20mm;
        }
        .scope-panel .info-box-header {
            margin: -1.5mm -1.5mm 1.5mm -1.5mm;
            border-radius: 0;
        }

        /* ===== SUMMARY + PAY ROW ===== */
        .summary-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5mm;
            align-items: start;
        }
        .summary-left {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5mm;
        }
        .summary-card {
            border: 0.5px solid #AAB8C7;
            border-radius: 2px;
            padding: 1.5mm;
            min-height: 15mm;
        }
        .totals {
            table-layout: fixed;
            font-size: 8px;
        }
        .totals td {
            height: 4.5mm;
            padding: 0.8mm 2mm;
        }
        .totals .label {
            width: 50%;
            font-weight: 700;
            text-transform: uppercase;
        }
        .totals .grand-label,
        .totals .grand-value {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            font-size: 9px;
        }
        .totals .grand-value {
            font-size: 12px;
        }

        /* ===== PAY BANNER ===== */
        .pay-banner {
            width: 100%;
            min-height: 7mm;
            border: 0.5px solid #C5D0DC;
            border-radius: 2px;
            background: #EAF1F8;
            display: grid;
            grid-template-columns: 10mm minmax(0, 1fr) 40mm;
            gap: 1mm;
            align-items: center;
            padding: 1mm 2.5mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .pay-banner .pay-icon {
            color: #062A55;
            font-size: 10px;
            font-weight: 800;
            text-align: center;
        }
        .pay-banner .pay-label {
            color: #062A55;
            font-size: 7.5px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .pay-banner .pay-sub {
            color: #374151;
            font-size: 5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .pay-banner .pay-amount {
            color: #062A55;
            font-size: 11px;
            font-weight: 800;
            text-align: right;
        }

        /* ===== CONDITIONS COMPACT (single row) ===== */
        .conditions-bar {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 1mm;
        }
        .condition-mini {
            border: 0.5px solid #AAB8C7;
            border-radius: 2px;
            padding: 0.8mm;
            text-align: center;
        }
        .condition-mini .cond-icon {
            width: 4mm;
            height: 4mm;
            border: 0.6px solid #062A55;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 800;
            color: #062A55;
            margin-bottom: 0.2mm;
        }
        .condition-mini .cond-title {
            color: #062A55;
            font-size: 5.2px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 0.2mm;
        }
        .condition-mini .cond-text {
            font-size: 4.8px;
            line-height: 1.1;
            color: #374151;
        }

        /* ===== BANK + SIGNATURE ===== */
        .bank-signature {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .detail-table td {
            height: 4.5mm;
            font-size: 6.5px;
        }
        .detail-table .heading {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5mm;
        }
        .signature-box {
            border: 0.5px solid #AAB8C7;
            min-height: 10mm;
            display: grid;
            grid-template-rows: 4mm 1fr;
        }
        .signature-title {
            background: #062A55;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 7px;
        }
        .signature-name {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1mm;
            font-weight: 700;
            min-height: 9mm;
        }

        /* ===== LEGAL BOX ===== */
        .legal-box {
            border: 0.5px solid #AAB8C7;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .legal-title {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            text-align: center;
            padding: 1mm;
            text-transform: uppercase;
            font-size: 7.5px;
        }
        .legal-text {
            padding: 1.5mm 3mm;
            font-size: 6.8px;
            line-height: 1.2;
            text-align: center;
        }

        /* ===== FOOTER ===== */
        .footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: end;
            gap: 4mm;
            min-height: 8mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .thanks {
            color: #062A55;
            font-size: 10px;
            font-style: italic;
            line-height: 1;
        }
        .service-footer {
            color: #062A55;
            text-align: center;
            font-size: 6.5px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.2;
        }

        /* ===== WATERMARK ===== */
        .watermark {
            position: absolute;
            left: 70mm;
            top: 50mm;
            z-index: 5;
            transform: rotate(-25deg);
            color: rgba(198, 0, 0, .48);
            border: 2px solid rgba(198, 0, 0, .50);
            padding: 1.5mm 7mm;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1.4px;
            pointer-events: none;
        }

        /* ===== VERIFY TABLE ===== */
        .verify-table {
            width: 100%;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            align-items: stretch;
            border: 0.5px solid #062A55;
        }
        .verify-cell {
            padding: 0.8mm 1.2mm;
            vertical-align: middle;
        }
        .verify-qr {
            width: 12mm;
            text-align: center;
            border-right: 0.5px solid #062A55;
            flex: 0 0 12mm;
        }
        .verify-qr img {
            width: 10mm;
            height: 10mm;
            display: block;
            margin: 0 auto;
        }
        .verify-info {
            font-size: 6px;
            line-height: 1.1;
            flex: 1;
        }
        .verify-badge {
            display: inline-block;
            font-weight: 800;
            letter-spacing: 1px;
            color: #062A55;
            border: 0.5px solid #062A55;
            border-radius: 2px;
            padding: .3mm 1mm;
            margin-bottom: .3mm;
        }
        .verify-code {
            font-family: "Courier New", Courier, monospace;
            font-weight: 800;
            font-size: 6.5px;
            letter-spacing: 1px;
        }

        @media print {
            html, body { background: #fff; }
            .invoice-page { margin: 0; }
        }
    </style>
</head>
<body>
<section class="invoice-page">
    @if($watermark)
        <div class="watermark">{{ $watermark }}</div>
    @endif

    <main class="document">
        <!-- HEADER -->
        <header class="header-top">
            <div class="logo-box">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo">
                @else
                    <div class="logo-initial">{{ $sellerInitial }}</div>
                @endif
            </div>

            <div>
                <div class="service-icons">
                    <div class="service-icon">
                        <span class="icon-symbol">✓</span><br>
                        INTERVENCIONES<br>GARANTIZADAS
                    </div>
                    <div class="service-icon">
                        <span class="icon-symbol">⚙</span><br>
                        TECNICOS<br>CUALIFICADOS
                    </div>
                    <div class="service-icon">
                        <span class="icon-symbol">✓</span><br>
                        REPUESTOS<br>ORIGINAL
                    </div>
                    <div class="service-icon">
                        <span class="icon-symbol">📞</span><br>
                        ATENCION<br>24/7
                    </div>
                    <div class="service-icon">
                        <span class="icon-symbol">+</span><br>
                        SERVICIOS<br>COMPLETOS
                    </div>
                </div>
            </div>

            <div class="doc-title-area">
                <div class="doc-title">{{ $documentTitle }}</div>
                @if(!$isQuotation)
                    <div class="doc-subtitle">DE INTERVENCION TECNICA</div>
                @endif
            </div>
        </header>

        <!-- TOP INFO ROW (4 columns, landscape) -->
        <section class="top-info-row">
            <!-- Panel 1: Datos fiscales del emisor -->
            <div class="info-box">
                <div class="info-box-header">DATOS FISCALES DEL EMISOR</div>
                <div class="info-box-body">
                    <div class="info-row">
                        <span class="info-label">Razon Social:</span>
                        <span class="info-value">{{ $invoice->seller_name ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ $sellerNifLabel }}:</span>
                        <span class="info-value">{{ $sellerNif ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Direccion:</span>
                        <span class="info-value">{{ $sellerAddress ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Poblacion:</span>
                        <span class="info-value">{{ $invoice->seller_city ?: 'N/A' }}</span>
                    </div>
                    @if($sellerPhone)
                    <div class="info-row">
                        <span class="info-label">Telefono:</span>
                        <span class="info-value">{{ $sellerPhone }}</span>
                    </div>
                    @endif
                    @if($sellerEmail)
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">{{ $sellerEmail }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Panel 2: Datos del cliente -->
            <div class="info-box">
                <div class="info-box-header">DATOS DEL CLIENTE</div>
                <div class="info-box-body">
                    <div class="info-row">
                        <span class="info-label">Cliente:</span>
                        <span class="info-value">{{ $clientName ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">NIF/CIF:</span>
                        <span class="info-value">{{ $clientTaxId ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Direccion:</span>
                        <span class="info-value">{{ $clientAddress ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Poblacion:</span>
                        <span class="info-value">{{ $clientCity ?: 'N/A' }}</span>
                    </div>
                    @if($clientPhone)
                    <div class="info-row">
                        <span class="info-label">Telefono:</span>
                        <span class="info-value">{{ $clientPhone }}</span>
                    </div>
                    @endif
                    @if($clientEmail)
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">{{ $clientEmail }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Panel 3: Detalles del presupuesto / factura -->
            <div class="info-box">
                <div class="info-box-header">{{ $isQuotation ? 'DETALLES DEL PRESUPUESTO' : 'DETALLES DE LA FACTURA' }}</div>
                <div class="info-box-body">
                    @if($isQuotation)
                    <div class="info-row">
                        <span class="info-label">Fecha emision:</span>
                        <span class="info-value">{{ $invoice->invoice_date?->format('d/m/Y') ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Validez:</span>
                        <span class="info-value">{{ $quotationValidUntil?->format('d/m/Y') ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Forma pago:</span>
                        <span class="info-value">{{ $paymentTermName }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Modelo:</span>
                        <span class="info-value">{{ $invoice->assigned_model ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">N° serie/Ref:</span>
                        <span class="info-value">{{ $invoice->serial_number ?: $invoice->reference ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Equipo:</span>
                        <span class="info-value">{{ $invoice->equipment_type ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ubicacion:</span>
                        <span class="info-value">{{ $invoice->work_location ?: 'N/A' }}</span>
                    </div>
                    @else
                    <div class="info-row">
                        <span class="info-label">Fecha emision:</span>
                        <span class="info-value">{{ $invoice->invoice_date?->format('d/m/Y') ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Vencimiento:</span>
                        <span class="info-value">{{ $invoice->due_date?->format('d/m/Y') ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Forma pago:</span>
                        <span class="info-value">{{ $paymentTermName }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Modelo:</span>
                        <span class="info-value">{{ $invoice->assigned_model ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">N° serie/Ref:</span>
                        <span class="info-value">{{ $invoice->serial_number ?: $invoice->reference ?: 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Equipo:</span>
                        <span class="info-value">{{ $invoice->equipment_type ?: 'N/A' }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Panel 4: Resumen -->
            <div class="info-box">
                <div class="info-box-header">{{ $isQuotation ? 'RESUMEN' : 'DATOS DE LA FACTURA' }}</div>
                <div class="info-box-body">
                    <div class="info-row">
                        <span class="info-label">Subtotal:</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->subtotal, $currency) }}</span>
                    </div>
                    @if($isQuotation)
                    <div class="info-row">
                        <span class="info-label">Desplazamiento:</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->transport_cost ?? 0, $currency) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Base imponible:</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->taxable_base ?? $invoice->subtotal, $currency) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IVA ({{ $invoice->tax_rate ?? 21 }}%):</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->tax_total, $currency) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IVA ({{ $invoice->tax2_rate ?? 10 }}%):</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->tax2_total ?? 0, $currency) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IVA ({{ $invoice->tax3_rate ?? 4 }}%):</span>
                        <span class="info-value right nowrap">{{ $money->format($invoice->tax3_total ?? 0, $currency) }}</span>
                    </div>
                    @else
                    <div class="info-row">
                        <span class="info-label">Vencimiento:</span>
                        <span class="info-value right nowrap">{{ $invoice->due_date?->format('d/m/Y') ?: 'N/A' }}</span>
                    </div>
                    @endif
                    <div class="info-row" style="margin-top: 1mm;">
                        <span class="info-label" style="font-size: 8.5px; font-weight: 900;">{{ $totalLabel }}:</span>
                        <span class="info-value right nowrap" style="font-size: 10px; font-weight: 900; color: #062A55;">{{ $money->format($invoice->total, $currency) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ITEMS TABLE + SCOPE PANEL -->
        <section class="items-section">
            <div>
                <div class="section-title"><span class="icon">✎</span> DETALLE DE LOS TRABAJOS Y SUMINISTROS</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th class="concept">DESCRIPCION DE LA ACTUACION</th>
                            <th class="qty">CANT.</th>
                            <th class="unit">PRECIO UNIT.</th>
                            <th class="amount">IMPORTE</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr>
                            <td class="concept wrap">{{ $item->description }}</td>
                            <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                            <td class="unit nowrap">{{ $money->format($item->unit_cost, $currency) }}</td>
                            <td class="amount nowrap">{{ $money->format($item->line_subtotal, $currency) }}</td>
                        </tr>
                        @endforeach
                        @for($i = 0; $i < $fillerRows; $i++)
                        <tr>
                            <td class="concept">&nbsp;</td>
                            <td class="qty">&nbsp;</td>
                            <td class="unit">&nbsp;</td>
                            <td class="amount">&nbsp;</td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <!-- Scope panel -->
            <div class="scope-panel">
                <div class="info-box-header">ALCANCE DEL SERVICIO</div>
                <div style="padding: 1.5mm;">
                    <p class="wrap" style="font-size: 7px; line-height: 1.25;">
                        {{ $invoice->observations ?: 'Este documento incluye los trabajos descritos en el detalle, materiales y desplazamiento dentro del area metropolitana de ' . ($invoice->seller_city ?: 'la ciudad.') }}
                    </p>
                    @if($invoice->diagnostic_summary)
                    <div style="margin-top: 1.5mm; border-top: 0.5px solid #AAB8C7; padding-top: 1mm;">
                        <div style="font-weight: 800; color: #062A55; font-size: 7px; margin-bottom: 0.5mm;">DIAGNOSTICO TECNICO</div>
                        <p class="wrap" style="font-size: 6.8px; line-height: 1.2;">{{ $invoice->diagnostic_summary }}</p>
                    </div>
                    @endif
                    @if($invoice->technical_conclusions)
                    <div style="margin-top: 1mm; border-top: 0.5px solid #AAB8C7; padding-top: 1mm;">
                        <div style="font-weight: 800; color: #062A55; font-size: 7px; margin-bottom: 0.5mm;">CONCLUSIONES TECNICAS</div>
                        <p class="wrap" style="font-size: 6.8px; line-height: 1.2;">{{ $invoice->technical_conclusions }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </section>

        <!-- SUMMARY ROW -->
        <section class="summary-row">
            <!-- Totals -->
            <table class="totals">
                @if(! $isQuotation)
                <tr>
                    <td class="label">IMP. RECIBIDO</td>
                    <td class="right nowrap">{{ $money->format($invoice->amount_received, $currency) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">SUBTOTAL</td>
                    <td class="right nowrap">{{ $money->format($invoice->subtotal, $currency) }}</td>
                </tr>
                <tr>
                    <td class="label">IVA</td>
                    <td class="right nowrap">{{ $money->format($invoice->tax_total, $currency) }}</td>
                </tr>
                @if(! $isQuotation && (float) $invoice->balance_due !== (float) $invoice->total)
                <tr>
                    <td class="label">BALANCE PENDIENTE</td>
                    <td class="right nowrap">{{ $money->format($invoice->balance_due, $currency) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="grand-label">{{ $totalLabel }}</td>
                    <td class="grand-value right nowrap">{{ $money->format($invoice->total, $currency) }}</td>
                </tr>
            </table>

            <!-- Observations -->
            <div class="summary-card">
                <div class="section-title"><span class="icon">OBS</span> OBSERVACIONES</div>
                <div style="font-size: 7px; line-height: 1.25;">{{ $invoice->observations ?: ' ' }}</div>
            </div>
        </section>

        <!-- PAY BANNER -->
        <section class="pay-banner">
            <div class="pay-icon">{{ $isQuotation ? 'CALC' : 'PAY' }}</div>
            <div>
                <div class="pay-label">{{ $payTotalLabel }}</div>
                <div class="pay-sub">(IVA INCLUIDO)</div>
            </div>
            <div class="pay-amount nowrap">{{ $money->format($invoice->total, $currency) }}</div>
        </section>

        <!-- MIDDLE SECTION: Acceptance + Payment Methods + Additional Info -->
        <section class="items-section" style="grid-template-columns: 1fr 1fr 1fr; gap: 1.5mm;">
            <div class="summary-card">
                <div class="section-title"><span class="icon">✓</span> ACEPTACION</div>
                <p class="wrap" style="font-size: 6.8px; line-height: 1.2;">
                    @if($isQuotation)
                        La aceptacion de este presupuesto implica conformidad con las condiciones descritas. Este presupuesto no implica reserva del servicio.
                    @else
                        La firma, aceptacion digital o pago del servicio confirma la conformidad del trabajo realizado. La garantia cubre exclusivamente la reparacion realizada y las piezas sustituidas.
                    @endif
                </p>
            </div>

            <div class="summary-card">
                <div class="section-title"><span class="icon">BANK</span> FORMAS DE PAGO</div>
                <div style="font-size: 6.8px; line-height: 1.2;">
                    @if($invoice->bankAccount)
                    <div style="margin-bottom: 1mm;">
                        <div style="font-weight: 800; color: #062A55;">Transferencia bancaria</div>
                        <div>{{ $invoice->bankAccount->bank_name }} - {{ $invoice->bankAccount->account_holder }}</div>
                        <div>{{ $invoice->bankAccount->iban ?: $invoice->bankAccount->account_number }}</div>
                    </div>
                    @endif
                    <div>
                        <div style="font-weight: 800; color: #062A55;">Efectivo</div>
                        <div>Consulte condiciones</div>
                    </div>
                </div>
            </div>

            <div class="summary-card">
                <div class="section-title"><span class="icon">INFO</span> INFO ADICIONAL</div>
                <div style="font-size: 6.8px; line-height: 1.2;">
                    @if($isQuotation)
                    <div style="margin-bottom: 1mm;">
                        <div style="font-weight: 800; color: #062A55;">VALIDO HASTA:</div>
                        <div style="font-weight: 800; color: #062A55; font-size: 9px;">{{ $quotationValidUntil?->format('d/m/Y') ?: '30 dias' }}</div>
                    </div>
                    @endif
                    <div>
                        <div style="font-weight: 800; color: #062A55;">ESTADO:</div>
                        <div style="text-transform: uppercase;">{{ ucfirst($status) }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONDITIONS COMPACT (2 rows x 5 columns) -->
        <section class="conditions-bar">
            <!-- 1. Garantia -->
            <div class="condition-mini">
                <div class="cond-icon">✓</div>
                <div class="cond-title">1. GARANTIA</div>
                <div class="cond-text">{{ $warrantyText }}</div>
            </div>
            <!-- 2. Exclusiones -->
            <div class="condition-mini">
                <div class="cond-icon">✗</div>
                <div class="cond-title">2. EXCLUSIONES</div>
                <div class="cond-text">No cubre danos por manipulacion externa, mal uso, desgaste natural o elementos ajenos.</div>
            </div>
            <!-- 3. Condiciones -->
            <div class="condition-mini">
                <div class="cond-icon">⚖</div>
                <div class="cond-title">3. CONDICIONES</div>
                <div class="cond-text">Sujeto a condiciones generales de la intervencion. Aceptacion implica conformidad.</div>
            </div>
            <!-- 4. Plazos -->
            <div class="condition-mini">
                <div class="cond-icon">!</div>
                <div class="cond-title">4. PLAZOS</div>
                <div class="cond-text">Plazos estimados, pueden variar segun disponibilidad de materiales.</div>
            </div>
            <!-- 5. Materiales -->
            <div class="condition-mini">
                <div class="cond-icon">⚙</div>
                <div class="cond-title">5. MATERIALES</div>
                <div class="cond-text">Materiales sustituidos pasan a propiedad del tecnico.</div>
            </div>
            <!-- 6. Modificaciones -->
            <div class="condition-mini">
                <div class="cond-icon">📄</div>
                <div class="cond-title">6. MODIFICACIONES</div>
                <div class="cond-text">Cambios en alcance deben ser comunicados y presupuestados.</div>
            </div>
            <!-- 7. Proteccion datos -->
            <div class="condition-mini">
                <div class="cond-icon">🔒</div>
                <div class="cond-title">7. DATOS</div>
                <div class="cond-text">RGPD (UE 2016/679). Datos conservados durante vigencia de garantia.</div>
            </div>
            <!-- 8. Cancelacion -->
            <div class="condition-mini">
                <div class="cond-icon">↺</div>
                <div class="cond-title">8. CANCELACION</div>
                <div class="cond-text">Cancelaciones con menos de 24h podran implicar coste de desplazamiento.</div>
            </div>
            <!-- 9. Aceptacion -->
            <div class="condition-mini">
                <div class="cond-icon">✓✓</div>
                <div class="cond-title">9. ACEPTACION</div>
                <div class="cond-text">La aceptacion implica conformidad. Firma o pago del documento.</div>
            </div>
            <!-- 10. Jurisdiccion -->
            <div class="condition-mini">
                <div class="cond-icon">🏛</div>
                <div class="cond-title">10. JURISDICCION</div>
                <div class="cond-text">Juzgados competentes: {{ $invoice->seller_city ?: 'la ciudad del emisor' }}.</div>
            </div>
        </section>

        <!-- BANK + SIGNATURE -->
        @if($isQuotation)
        <section class="bank-signature">
            <div>
                <div style="border: 0.5px solid #AAB8C7; padding: 1mm; margin-bottom: 1mm; text-align: center; font-weight: 800; background: #062A55; color: #fff; font-size: 7px; text-transform: uppercase;">
                    {{ $warrantyText }}
                </div>
                <div style="border: 0.5px solid #AAB8C7; padding: 1mm; text-align: center; font-weight: 800; background: #d90000; color: #fff; font-size: 7px; text-transform: uppercase;">
                    PAGA Y SEÑAL / EQUIPO Y MATERIALES / AVANCE DE PAGO
                </div>
                @if($invoice->bankAccount)
                <div style="border-top: 0.5px solid #AAB8C7; border-bottom: 0.5px solid #AAB8C7; padding: 1mm; display: grid; grid-template-columns: 25mm minmax(0, 1fr); align-items: center;">
                    <div style="background: #062A55; color: #fff; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 7.5px; font-weight: 800; text-transform: uppercase;">
                        CUENTA DE<br>BANCO
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; text-align: center; padding: 1mm; font-size: 7px; font-weight: 700;">
                        {{ $invoice->bankAccount->bank_name }} - {{ $invoice->bankAccount->account_holder }}<br>
                        {{ $invoice->bankAccount->iban ?: $invoice->bankAccount->account_number ?: ' ' }}
                    </div>
                </div>
                @endif
                <div style="border: 0.5px solid #AAB8C7; padding: 1mm; text-align: center; font-weight: 800; font-size: 6px; margin-top: 1mm;">
                    SOMOS TECNICOS HOMOLOGOS Y GARANTIZAMOS 100% NUESTROS SERVICIOS.
                </div>
            </div>

            <section class="signature-grid">
                <div class="signature-box">
                    <div class="signature-title">RECIBIDO POR</div>
                    <div class="signature-name wrap">{{ $invoice->received_by ?: ' ' }}</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">PREPARADO POR</div>
                    <div class="signature-name wrap">{{ $invoice->prepared_by ?: ' ' }}</div>
                </div>
            </section>
        </section>
        @else
        <!-- INVOICE-SPECIFIC: Bank + Signature + Legal -->
        <section class="bank-signature">
            <table class="detail-table">
                <tr>
                    <td class="heading" colspan="2">CUENTAS BANCARIAS</td>
                </tr>
                <tr>
                    <td class="bold" style="width: 25mm;">Banco</td>
                    <td class="wrap">{{ $invoice->bankAccount?->bank_name ?: ' ' }}</td>
                </tr>
                <tr>
                    <td class="bold">Titular</td>
                    <td class="wrap">{{ $invoice->bankAccount?->account_holder ?: ' ' }}</td>
                </tr>
                <tr>
                    <td class="bold">Cuenta</td>
                    <td class="wrap">
                        @if($invoice->bankAccount)
                            {{ $invoice->bankAccount->iban ?: $invoice->bankAccount->account_number ?: ' ' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="copy-badge" colspan="2" style="background: #062A55; color: #fff; text-align: center; font-weight: 800;">ORIGINAL: CLIENTE &nbsp;&nbsp;&nbsp; COPIA: VENDEDOR</td>
                </tr>
            </table>

            <section class="signature-grid">
                <div class="signature-box">
                    <div class="signature-title">RECIBIDO POR</div>
                    <div class="signature-name wrap">{{ $invoice->received_by ?: ' ' }}</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">PREPARADO POR</div>
                    <div class="signature-name wrap">{{ $invoice->prepared_by ?: ' ' }}</div>
                </div>
            </section>
        </section>

        <section class="legal-box">
            <div class="legal-title">CONFORMIDAD DEL CLIENTE</div>
            <div class="legal-text wrap">{{ $legalText }}</div>
        </section>
        @endif

        <!-- VERIFICATION TABLE -->
        @if ($isSigned)
        <div class="verify-table">
            @if ($verificationQr)
            <div class="verify-cell verify-qr">
                <img src="{{ $verificationQr }}" alt="Codigo de verificacion">
            </div>
            @endif
            <div class="verify-cell verify-info">
                <span class="verify-badge">DOCUMENTO ORIGINAL</span><br>
                Documento emitido y autenticado por el sistema. Verifique su autenticidad
                escaneando el codigo QR o consultando el codigo de seguridad en el sistema:<br>
                Codigo de seguridad: <span class="verify-code">{{ $verificationCode }}</span><br>
                Cualquier ejemplar cuyo total o datos no coincidan con los mostrados al verificar
                este codigo es una copia no autentica.
            </div>
        </div>
        @endif

        <!-- FOOTER -->
        <footer class="footer">
            <div class="thanks">Gracias por confiar en nosotros</div>
            <div class="service-footer">
                Servicio tecnico especializado<br>
                Instalaciones - reparaciones - mantenimiento<br>
                preventivo y correctivo
            </div>
        </footer>
    </main>
</section>

</body>
</html>
