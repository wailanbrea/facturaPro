@inject('money', 'App\Services\CurrencyFormatterService')
@inject('signature', 'App\Services\InvoiceSignatureService')
@inject('qr', 'App\Services\QrCodeService')
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
    $numberLabel = $isQuotation ? 'N.&ordm; PRESUPUESTO' : 'FACTURA N.&ordm;';
    $totalLabel = $isQuotation ? 'TOTAL PRESUPUESTO' : 'TOTAL FACTURA';
    $payTotalLabel = $isQuotation ? 'TOTAL PRESUPUESTO' : 'TOTAL A PAGAR';
    $quotationValidUntil = $isQuotation
        ? $invoice->invoice_date?->copy()->addDays(30)
        : $invoice->due_date;

    // Prefer the catalog warranty to repair legacy documents that saved the generic legal text.
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
    $client = $invoice->client;
    $clientPhone = $client?->phone;
    $clientEmail = $client?->email;
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
    $conceptText = $invoice->observations
        ?: $invoice->items->pluck('description')->filter()->implode("\n");
    $paymentTermName = $invoice->paymentTerm?->name ?: ' ';
    $dueText = $invoice->due_date?->format('d/m/Y') ?: ($paymentTermName !== ' ' ? $paymentTermName : 'CONTADO');

    // Authenticity block: only rendered once the invoice is sealed (issued).
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
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111827;
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.25;
        }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .invoice-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 10mm 10mm 9mm;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .invoice-page::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 0;
            width: 34mm;
            height: 24mm;
            background: #062A55;
            border-top-left-radius: 28mm;
            z-index: 0;
        }
        .document {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 5mm;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
            width: 100%;
        }
        td, th {
            border: 1px solid #AAB8C7;
            padding: 2.2mm 2.6mm;
            vertical-align: middle;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { color: #374151; }
        .nowrap { white-space: nowrap; }
        .wrap {
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .title-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 88mm;
            gap: 8mm;
            align-items: start;
        }
        .brand {
            display: grid;
            grid-template-columns: 26mm minmax(0, 1fr);
            gap: 6mm;
            align-items: start;
        }
        .logo-box {
            width: 26mm;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .logo-box img {
            max-width: 26mm;
            max-height: 24mm;
            object-fit: contain;
        }
        .logo-initial {
            width: 22mm;
            height: 22mm;
            border: 1.2mm solid #062A55;
            color: #062A55;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
        }
        .seller-name {
            color: #062A55;
            font-size: 24px;
            line-height: 1;
            font-weight: 800;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }
        .seller-subtitle {
            margin-top: 3mm;
            color: #062A55;
            font-size: 9.5px;
            letter-spacing: 1px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .seller-list {
            margin-top: 6mm;
            display: grid;
            gap: 2.2mm;
            color: #111827;
            font-size: 10.5px;
        }
        .seller-line {
            display: grid;
            grid-template-columns: 7mm minmax(0, 1fr);
            align-items: start;
            gap: 2.3mm;
        }
        .icon {
            color: #062A55;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            text-align: center;
        }
        .doc-side {
            text-align: right;
        }
        .doc-title {
            color: #062A55;
            font-size: 40px;
            line-height: .95;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
            margin: 1mm 0 8mm;
        }
        .doc-table {
            table-layout: fixed;
            font-size: 10.8px;
        }
        .doc-table td {
            height: 10.2mm;
            padding: 2mm 3mm;
        }
        .doc-table .label {
            width: 46%;
            background: #062A55;
            color: #fff;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
        }
        .doc-table .value {
            background: #fff;
            color: #111827;
            font-weight: 600;
            text-align: center;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 2mm;
            color: #062A55;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }
        .panel {
            border: 1px solid #AAB8C7;
            border-radius: 3px;
            padding: 4mm;
            min-height: 32mm;
        }
        .top-panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10mm;
        }
        .top-panels.invoice-client-only {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .data-grid {
            display: grid;
            grid-template-columns: 30mm minmax(0, 1fr);
            row-gap: 2.4mm;
            column-gap: 4mm;
            font-size: 10.2px;
        }
        .invoice-client-only .data-grid {
            grid-template-columns: 27mm minmax(0, 1fr) 28mm minmax(0, 1fr);
        }
        .label-text {
            font-weight: 800;
            color: #111827;
        }
        .items {
            table-layout: fixed;
            page-break-inside: auto;
        }
        .items th {
            background: #062A55;
            color: #fff;
            height: 9mm;
            font-size: 10.3px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .items td {
            height: 10.5mm;
            font-size: 9.8px;
        }
        .items tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .items .concept { width: 45%; }
        .items .qty { width: 15%; text-align: center; }
        .items .unit { width: 20%; text-align: right; }
        .items .amount { width: 20%; text-align: right; }
        .summary-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 82mm;
            gap: 8mm;
            align-items: start;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .notes-panel {
            min-height: 33mm;
        }
        .notes-panel .section-title {
            margin-bottom: 3mm;
        }
        .notes-text {
            font-size: 9.5px;
            line-height: 1.45;
            white-space: pre-line;
        }
        .totals {
            table-layout: fixed;
            font-size: 11px;
        }
        .totals td {
            height: 10.1mm;
            padding: 2mm 4mm;
        }
        .totals .label {
            width: 52%;
            font-weight: 700;
            text-transform: uppercase;
        }
        .totals .grand-label,
        .totals .grand-value {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            font-size: 12px;
        }
        .totals .grand-value {
            font-size: 18px;
        }
        .pay-banner {
            margin-left: auto;
            width: 140mm;
            min-height: 16mm;
            border: 1px solid #C5D0DC;
            border-radius: 3px;
            background: #EAF1F8;
            display: grid;
            grid-template-columns: 20mm minmax(0, 1fr) 48mm;
            gap: 2mm;
            align-items: center;
            padding: 3mm 6mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .pay-banner .pay-icon {
            color: #062A55;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
        }
        .pay-banner .pay-label {
            color: #062A55;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .pay-banner .pay-sub {
            color: #374151;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .pay-banner .pay-amount {
            color: #062A55;
            font-size: 23px;
            font-weight: 800;
            text-align: right;
        }
        .conditions {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .condition-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5mm;
            border-bottom: 1px solid #D3DCE6;
            padding-bottom: 5mm;
        }
        .condition {
            display: grid;
            grid-template-columns: 7mm minmax(0, 1fr);
            gap: 2mm;
            font-size: 8.6px;
            line-height: 1.35;
        }
        .check {
            width: 5mm;
            height: 5mm;
            border: 1.2px solid #062A55;
            border-radius: 50%;
            color: #062A55;
            font-size: 9px;
            line-height: 4.5mm;
            text-align: center;
            font-weight: 800;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border-bottom: 1px solid #D3DCE6;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .info-card {
            padding: 3mm 5mm;
            text-align: center;
            min-height: 28mm;
            border-right: 1px solid #D3DCE6;
        }
        .info-card:last-child {
            border-right: 0;
        }
        .info-symbol {
            color: #062A55;
            font-size: 19px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 2mm;
        }
        .info-title {
            color: #062A55;
            font-size: 8.7px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 1mm;
        }
        .info-text {
            font-size: 8px;
            line-height: 1.28;
        }
        .bank-signature {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .detail-table td {
            height: 9mm;
            font-size: 9.2px;
        }
        .detail-table .heading {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .copy-badge {
            background: #062A55;
            color: #fff;
            text-align: center;
            font-weight: 800;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4mm;
        }
        .signature-box {
            border: 1px solid #AAB8C7;
            min-height: 24mm;
            display: grid;
            grid-template-rows: 8mm 1fr;
        }
        .signature-title {
            background: #062A55;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            text-transform: uppercase;
        }
        .signature-name {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2mm;
            font-weight: 700;
            min-height: 16mm;
        }
        .legal-box {
            border: 1px solid #AAB8C7;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .legal-title {
            background: #062A55;
            color: #fff;
            font-weight: 800;
            text-align: center;
            padding: 2mm;
            text-transform: uppercase;
        }
        .legal-text {
            padding: 3mm 5mm;
            font-size: 8.8px;
            line-height: 1.38;
            text-align: center;
        }
        .quotation-legacy {
            border: 1px solid #AAB8C7;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .quotation-legacy .blue-row {
            background: #062A55;
            color: #fff;
            text-align: center;
            font-weight: 800;
            padding: 2mm;
            text-transform: uppercase;
        }
        .quotation-legacy .advance {
            background: #d90000;
            color: #fff;
            text-align: center;
            font-weight: 800;
            padding: 2mm;
        }
        .quotation-bank {
            display: grid;
            grid-template-columns: 34mm minmax(0, 1fr);
            min-height: 12mm;
            border-top: 1px solid #AAB8C7;
            border-bottom: 1px solid #AAB8C7;
        }
        .quotation-bank-label {
            background: #062A55;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .quotation-bank-value {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2mm;
            font-size: 10px;
            font-weight: 800;
        }
        .footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: end;
            gap: 5mm;
            min-height: 16mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .thanks {
            color: #062A55;
            font-size: 17px;
            font-style: italic;
            line-height: 1;
        }
        .service-footer {
            color: #062A55;
            text-align: center;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.4;
        }
        .watermark {
            position: absolute;
            left: 62mm;
            top: 115mm;
            z-index: 5;
            transform: rotate(-25deg);
            color: rgba(198, 0, 0, .48);
            border: 2px solid rgba(198, 0, 0, .50);
            padding: 2mm 9mm;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1.4px;
            pointer-events: none;
        }
        .verify-table {
            width: 100%;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            align-items: stretch;
            border: 1px solid #062A55;
        }
        .verify-cell {
            padding: 1.2mm 1.8mm;
            vertical-align: middle;
        }
        .verify-qr {
            width: 17mm;
            text-align: center;
            border-right: 1px solid #062A55;
            flex: 0 0 17mm;
        }
        .verify-qr img {
            width: 14mm;
            height: 14mm;
            display: block;
            margin: 0 auto;
        }
        .verify-info {
            font-size: 7.4px;
            line-height: 1.16;
            flex: 1;
        }
        .verify-badge {
            display: inline-block;
            font-weight: 800;
            letter-spacing: 1px;
            color: #062A55;
            border: 1px solid #062A55;
            border-radius: 2px;
            padding: .3mm 1.2mm;
            margin-bottom: .3mm;
        }
        .verify-code {
            font-family: "Courier New", Courier, monospace;
            font-weight: 800;
            font-size: 8px;
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
        <header class="title-row">
            <section class="brand">
                <div class="logo-box">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="Logo">
                    @else
                        <div class="logo-initial">{{ $sellerInitial }}</div>
                    @endif
                </div>
                <div>
                    <div class="seller-name">{{ $invoice->seller_name ?: 'FacturaPro' }}</div>
                    <div class="seller-subtitle">Servicio tecnico especializado</div>
                    <div class="seller-list">
                        @if($sellerPhone)
                            <div class="seller-line"><span class="icon">TEL</span><span class="wrap">{{ $sellerPhone }}</span></div>
                        @endif
                        @if($sellerEmail)
                            <div class="seller-line"><span class="icon">MAIL</span><span class="wrap">{{ $sellerEmail }}</span></div>
                        @endif
                        @if($sellerAddress)
                            <div class="seller-line"><span class="icon">DIR</span><span class="wrap">{{ $sellerAddress }}</span></div>
                        @endif
                        @if($invoice->seller_tax_id)
                            <div class="seller-line"><span class="icon">ID</span><span class="wrap">NIF: {{ $invoice->seller_tax_id }}</span></div>
                        @endif
                    </div>
                </div>
            </section>

            <aside class="doc-side">
                <div class="doc-title">{{ $documentTitle }}</div>
                <table class="doc-table">
                    <tr>
                        <td class="label">{!! $numberLabel !!}</td>
                        <td class="value">{{ $invoice->invoice_number ?? 'BORRADOR' }}</td>
                    </tr>
                    <tr>
                        <td class="label">FECHA:</td>
                        <td class="value">{{ $invoice->invoice_date?->format('d/m/Y') ?: 'N/A' }}</td>
                    </tr>
                    @if($isQuotation)
                        <tr>
                            <td class="label">VALIDEZ</td>
                            <td class="value">30 DIAS{{ $quotationValidUntil ? ' - '.$quotationValidUntil->format('d/m/Y') : '' }}</td>
                        </tr>
                        <tr>
                            <td class="label">TECNICO RESPONSABLE</td>
                            <td class="value">{{ $invoice->prepared_by ?: $invoice->seller_name ?: 'Departamento Tecnico' }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="label">FORMA DE PAGO</td>
                            <td class="value">{{ $paymentTermName }}</td>
                        </tr>
                        <tr>
                            <td class="label">VENCIMIENTO</td>
                            <td class="value">{{ $dueText }}</td>
                        </tr>
                    @endif
                </table>
            </aside>
        </header>

        <section class="top-panels {{ $isQuotation ? '' : 'invoice-client-only' }}">
            <div>
                <div class="section-title"><span class="icon">USR</span> DATOS DEL CLIENTE</div>
                <div class="panel">
                    <div class="data-grid">
                        <div class="label-text">Cliente:</div>
                        <div class="wrap">{{ $invoice->client_name ?: 'N/A' }}</div>
                        <div class="label-text">NIF/CIF:</div>
                        <div class="wrap">{{ $invoice->client_tax_id ?: 'N/A' }}</div>
                        <div class="label-text">Direccion:</div>
                        <div class="wrap">{{ $invoice->client_address ?: 'N/A' }}</div>
                        <div class="label-text">Poblacion:</div>
                        <div class="wrap">{{ $invoice->client_city ?: 'N/A' }}</div>
                        <div class="label-text">Telefono:</div>
                        <div class="wrap">{{ $clientPhone ?: ' ' }}</div>
                        <div class="label-text">Email:</div>
                        <div class="wrap">{{ $clientEmail ?: ' ' }}</div>
                    </div>
                </div>
            </div>

            @if($isQuotation)
                <div>
                    <div class="section-title"><span class="icon">DOC</span> CONCEPTO</div>
                    <div class="panel">
                        <div class="notes-text wrap">{{ $conceptText ?: ' ' }}</div>
                    </div>
                </div>
            @endif
        </section>

        <table class="items">
            <thead>
                <tr>
                    <th class="concept">CONCEPTO</th>
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

        <section class="summary-row">
            <div class="notes-panel panel">
                <div class="section-title"><span class="icon">OBS</span> OBSERVACIONES</div>
                <div class="notes-text wrap">{{ $invoice->observations ?: ' ' }}</div>
            </div>

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
        </section>

        <section class="pay-banner">
            <div class="pay-icon">{{ $isQuotation ? 'CALC' : 'PAY' }}</div>
            <div>
                <div class="pay-label">{{ $payTotalLabel }}</div>
                <div class="pay-sub">(IVA INCLUIDO)</div>
            </div>
            <div class="pay-amount nowrap">{{ $money->format($invoice->total, $currency) }}</div>
        </section>

        @if($isQuotation)
            <section class="conditions">
                <div class="section-title"><span class="icon">OK</span> CONDICIONES DEL PRESUPUESTO</div>
                <div class="condition-grid">
                    <div class="condition"><span class="check">✓</span><span>El presupuesto tiene una validez de 30 dias desde la fecha de emision.</span></div>
                    <div class="condition"><span class="check">✓</span><span>El material podra requerir anticipo para su reserva.</span></div>
                    <div class="condition"><span class="check">✓</span><span>La aceptacion del presente presupuesto implica conformidad con las condiciones indicadas.</span></div>
                    <div class="condition"><span class="check">✓</span><span>El inicio de los trabajos queda sujeto a confirmacion del presupuesto y disponibilidad.</span></div>
                    <div class="condition"><span class="check">✓</span><span>El importe restante se abonara a la finalizacion de los trabajos.</span></div>
                    <div class="condition"><span class="check">✓</span><span>Cualquier trabajo adicional no contemplado sera comunicado y presupuestado previamente.</span></div>
                </div>
            </section>

            <section class="quotation-legacy">
                <div class="blue-row">{{ $warrantyText }}</div>
                <div class="advance">PAGA Y SE&Ntilde;AL EQUIPO Y MATERIALES AVANCE DE PAGO</div>
                <div class="quotation-bank">
                    <div class="quotation-bank-label">CUENTA DE<br>BANCO</div>
                    <div class="quotation-bank-value">
                        @if($invoice->bankAccount)
                            {{ $invoice->bankAccount->bank_name }} - {{ $invoice->bankAccount->account_holder }}<br>
                            {{ $invoice->bankAccount->iban ?: $invoice->bankAccount->account_number ?: ' ' }}
                        @endif
                    </div>
                </div>
                <div class="advance">SOMOS TECNICOS HOMOLOGOS Y GARANTIZAMOS 100% NUESTROS SERVICIOS.</div>
            </section>

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
        @else
            <section class="bank-signature">
                <table class="detail-table">
                    <tr>
                        <td class="heading" colspan="2">CUENTAS BANCARIAS</td>
                    </tr>
                    <tr>
                        <td class="bold" style="width: 30mm;">Banco</td>
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
                        <td class="copy-badge" colspan="2">ORIGINAL: CLIENTE&nbsp;&nbsp;&nbsp; COPIA: VENDEDOR</td>
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

        <section class="info-grid">
            <div class="info-card">
                <div class="info-symbol">SH</div>
                <div class="info-title">GARANTIA</div>
                <div class="info-text">{{ $warrantyText }}</div>
            </div>
            <div class="info-card">
                <div class="info-symbol">TL</div>
                <div class="info-title">TRABAJOS REALIZADOS</div>
                <div class="info-text">Los trabajos se han ejecutado conforme al documento aceptado y segun las condiciones acordadas.</div>
            </div>
            <div class="info-card">
                <div class="info-symbol">EX</div>
                <div class="info-title">EXCLUSIONES</div>
                <div class="info-text">No quedan incluidas averias derivadas de elementos ajenos a la intervencion realizada.</div>
            </div>
            <div class="info-card">
                <div class="info-symbol">ST</div>
                <div class="info-title">SERVICIO TECNICO</div>
                <div class="info-text">Para cualquier incidencia, contacte con nuestro servicio tecnico indicando el numero del documento.</div>
            </div>
        </section>

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
                    escaneando el codigo QR o consultando el codigo de seguridad en el sistema:
                    <br>
                    Codigo de seguridad: <span class="verify-code">{{ $verificationCode }}</span><br>
                    Cualquier ejemplar cuyo total o datos no coincidan con los mostrados al verificar
                    este codigo es una copia no autentica.
                </div>
            </div>
        @endif

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
