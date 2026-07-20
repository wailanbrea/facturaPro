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
    // Prefer the catalog warranty to repair legacy documents that saved the generic legal text.
    $warrantyText = $invoice->warranty?->full_text
        ?: $invoice->warranty_text
        ?: 'GARANTIA SEGUN CONDICIONES DEL FABRICANTE';
    $documentTitle = $isQuotation ? 'PRESUPUESTO' : 'FACTURA';
    $dateLabel = 'FECHA:';
    $numberLabel = $isQuotation ? 'PRESUPUESTO NO.' : 'FACTURA NO.';
    $quotationValidUntil = $isQuotation
        ? $invoice->invoice_date?->copy()->addDays(30)
        : $invoice->due_date;
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
    $legalText = $invoice->legal_text
        ?: $invoice->conformity_text
        ?: 'La firma, aceptacion digital o pago del servicio confirma la conformidad del trabajo realizado. La garantia cubre exclusivamente la reparacion realizada y las piezas sustituidas, excluyendo averias derivadas de manipulacion externa, mal uso o desgaste natural.';
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
    $visibleItems = $invoice->items;

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
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 1.2;
        }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .invoice-page {
            width: 210mm;
            margin: 0 auto;
            padding: 8mm 10mm;
            background: #fff;
            position: relative;
            overflow: visible;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
            width: 100%;
        }
        td, th {
            border: 1px solid #000;
            padding: 2mm 2.2mm;
            vertical-align: middle;
        }
        .blue {
            background: #1f4e79;
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .nowrap { white-space: nowrap; }
        .wrap {
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .page-grid {
            display: grid;
            row-gap: 2mm;
        }
        .page-grid.invoice-layout {
            grid-template-rows: auto auto 4mm auto auto auto auto auto;
        }
        .page-grid.quotation-layout {
            grid-template-rows: auto auto 4mm auto auto auto;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 42mm minmax(0, 1fr) 62mm;
            gap: 3mm;
            align-items: stretch;
        }
        .logo-cell {
            border: 1px solid #000;
            height: 36mm;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: 2mm;
        }
        .logo-cell img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .logo-initial {
            width: 24mm;
            height: 24mm;
            background: #1f4e79;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            border: 1px solid #000;
        }
        .seller-card {
            border: 1px solid #000;
            height: 36mm;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3mm;
            text-align: center;
            overflow: hidden;
        }
        .seller-name {
            display: block;
            max-width: 100%;
            font-size: 10.5px;
            line-height: 1.25;
            font-weight: 700;
            text-transform: uppercase;
            overflow-wrap: break-word;
            word-break: normal;
            hyphens: none;
        }
        .seller-detail {
            display: block;
            max-width: 100%;
            margin-top: 1.1mm;
            font-size: 8.6px;
            line-height: 1.15;
            font-weight: 700;
            text-transform: uppercase;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .document-table th {
            height: 11mm;
            font-size: 19px;
            letter-spacing: .4px;
            text-align: center;
        }
        .document-table td {
            height: 6.4mm;
            padding: 1.4mm 1.8mm;
            font-size: 10px;
        }
        .document-table .label {
            width: 31mm;
            font-weight: 700;
            background: #f3f6fb;
        }
        .document-table .value {
            font-weight: 700;
            text-align: right;
        }
        .client-table td {
            height: 8.5mm;
            font-size: 11px;
        }
        .client-table .client-label {
            width: 36mm;
        }
        .client-table .client-city-label {
            width: 22mm;
        }
        .client-table .client-city-value {
            width: 42mm;
        }
        .items {
            table-layout: fixed;
        }
        .items th {
            height: 8mm;
            font-size: 10.5px;
            text-align: center;
            padding: 1.6mm 1mm;
        }
        .items td {
            height: 10mm;
            padding: 1.4mm 1.6mm;
            font-size: 10px;
        }
        .items .desc { width: 38%; }
        .items .qty { width: 9%; text-align: center; }
        .items .cost { width: 15%; text-align: right; }
        .items .tax { width: 11%; text-align: right; }
        .items .unit { width: 15%; text-align: right; }
        .items .total { width: 12%; text-align: right; }
        .watermark {
            position: absolute;
            left: 64mm;
            top: 103mm;
            z-index: 5;
            transform: rotate(-25deg);
            color: rgba(198, 0, 0, .58);
            border: 2px solid rgba(198, 0, 0, .55);
            padding: 2mm 9mm;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 1.6px;
            pointer-events: none;
        }
        .middle-grid {
            display: grid;
            grid-template-columns: 1fr 58mm;
            gap: 3mm;
            align-items: stretch;
        }
        .notes-table {
            height: 39mm;
        }
        .notes-table td {
            padding: 2mm;
        }
        .warranty-row {
            height: 9mm;
            text-align: center;
            font-size: 10.5px;
        }
        .observations-label {
            height: 8mm;
            text-align: center;
        }
        .observations-box {
            height: 22mm;
            text-align: center;
            font-size: 10px;
            line-height: 1.25;
            overflow: hidden;
        }
        .totals-table {
            height: 43mm;
        }
        .totals-table td {
            height: 8.5mm;
            padding: 1.7mm 2mm;
            font-size: 10.5px;
        }
        .totals-table .label {
            width: 30mm;
            font-weight: 700;
            background: #f3f6fb;
        }
        .totals-table .received {
            background: #70ad47;
            color: #000;
            font-weight: 800;
        }
        .totals-table .grand-label,
        .totals-table .grand-value {
            font-size: 12px;
            font-weight: 800;
        }
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 58mm;
            gap: 3mm;
            align-items: stretch;
        }
        .quotation-side {
            display: grid;
            grid-template-rows: auto 3mm auto;
            align-content: start;
        }
        .bank-table,
        .signature-table {
            height: 40mm;
            table-layout: fixed;
        }
        .bank-table td,
        .signature-table td {
            padding: 1.6mm 2mm;
            font-size: 10px;
        }
        .bank-title,
        .signature-title {
            height: 7mm;
            text-align: center;
            font-size: 10.5px;
        }
        .bank-name {
            height: 6mm;
            text-align: center;
            font-weight: 800;
            font-size: 12px;
        }
        .bank-details {
            height: 16mm;
            line-height: 1.35;
            overflow: hidden;
        }
        .copy-badge {
            background: #c00000;
            color: #fff;
            text-align: center;
            font-weight: 800;
            font-size: 11px;
            line-height: 1.25;
            height: 10mm;
        }
        .signature-name {
            height: 13mm;
            text-align: center;
            font-weight: 700;
        }
        .prepared-name {
            height: 13mm;
            text-align: center;
            font-weight: 700;
        }
        .legal-table td {
            text-align: center;
        }
        .legal-title {
            height: 6mm;
            font-size: 10.5px;
        }
        .legal-text {
            height: 13mm;
            font-size: 9.4px;
            font-weight: 700;
            line-height: 1.25;
            padding: 2mm 6mm;
            overflow: hidden;
        }
        .quotation-bottom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        .quotation-bottom td {
            border: 1px solid #000;
            padding: 1.4mm 2mm;
            font-size: 10.4px;
        }
        .quotation-bottom .blue-row {
            background: #1f4e79;
            color: #fff;
            text-align: center;
            font-weight: 800;
        }
        .quotation-bottom .warranty {
            height: 7mm;
        }
        .quotation-bottom .observations {
            height: 23mm;
            text-align: center;
            font-weight: 800;
            line-height: 1.25;
            overflow: hidden;
        }
        .quotation-bottom .advance {
            background: #f00000;
            color: #fff;
            text-align: center;
            font-weight: 800;
            height: 7mm;
        }
        .quotation-bottom .bank-label {
            background: #1f4e79;
            color: #fff;
            width: 36mm;
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            line-height: 1.1;
        }
        .quotation-bottom .bank-value {
            text-align: center;
            font-weight: 800;
            font-size: 12px;
            line-height: 1.25;
        }
        .quotation-bottom .service-guarantee {
            background: #f00000;
            color: #fff;
            text-align: center;
            font-weight: 800;
            font-size: 12px;
            line-height: 1.25;
            height: 15mm;
        }
        .small {
            font-size: 9.4px;
        }
        .verify-table {
            width: 100%;
            margin-top: 0;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            align-items: stretch;
            border: 1px solid #1f3b73;
        }
        .verify-cell {
            padding: .8mm 1.5mm;
            vertical-align: middle;
        }
        .verify-qr {
            width: 15mm;
            text-align: center;
            border-right: 1px solid #1f3b73;
            flex: 0 0 15mm;
        }
        .verify-qr img {
            width: 13mm;
            height: 13mm;
            display: block;
            margin: 0 auto;
        }
        .verify-info {
            font-size: 7.2px;
            line-height: 1.12;
            flex: 1;
        }
        .verify-badge {
            display: inline-block;
            font-weight: 700;
            letter-spacing: 1px;
            color: #1f3b73;
            border: 1px solid #1f3b73;
            border-radius: 2px;
            padding: 0.2mm 1.2mm;
            margin-bottom: .25mm;
        }
        .verify-code {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
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

    <div class="page-grid {{ $isQuotation ? 'quotation-layout' : 'invoice-layout' }}">
        <header class="header-grid">
            <div class="logo-cell">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo">
                @else
                    <div class="logo-initial">{{ $sellerInitial }}</div>
                @endif
            </div>

            <div class="seller-card">
                <div>
                    <span class="seller-name">{{ $invoice->seller_name ?: 'FacturaPro' }}</span>
                    @if($invoice->seller_tax_id)
                        <span class="seller-detail">{{ $invoice->seller_tax_id }}</span>
                    @endif
                    @if($invoice->seller_address)
                        <span class="seller-detail">{{ $invoice->seller_address }}</span>
                    @endif
                    @if($invoice->seller_city)
                        <span class="seller-detail">{{ $invoice->seller_city }}</span>
                    @endif
                </div>
            </div>

            <table class="document-table">
                <tr>
                    <th class="blue" colspan="2">{{ $documentTitle }}</th>
                </tr>
                <tr>
                    <td class="label">{{ $numberLabel }}</td>
                    <td class="value">{{ $invoice->invoice_number ?? 'BORRADOR' }}</td>
                </tr>
                <tr>
                    <td class="label">{{ $dateLabel }}</td>
                    <td class="value">{{ $invoice->invoice_date?->format('d/m/Y') ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">VENCIMIENTO:</td>
                    <td class="value">{{ ($isQuotation ? $quotationValidUntil : $invoice->due_date)?->format('d/m/Y') ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">TERMINO DE PAGO:</td>
                    <td class="value">{{ $invoice->paymentTerm?->name ?? 'N/A' }}</td>
                </tr>
            </table>
        </header>

        <table class="client-table">
            <tr>
                <td class="blue client-label">FACTURAR A:</td>
                <td class="bold wrap" colspan="3">
                    {{ $invoice->client_name }}
                    @if($invoice->client_tax_id)
                        <span class="small"> - {{ $invoice->client_tax_id }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="blue client-label">DIRECCION:</td>
                <td class="wrap">{{ $invoice->client_address ?: 'N/A' }}</td>
                <td class="blue client-city-label">CIUDAD:</td>
                <td class="wrap client-city-value">{{ $invoice->client_city ?: 'N/A' }}</td>
            </tr>
        </table>

        <div></div>

        <table class="items">
            <thead>
                <tr>
                    <th class="blue desc">DESCRIPCION</th>
                    <th class="blue qty">CANTIDAD</th>
                    <th class="blue cost">COSTO UNITARIO</th>
                    <th class="blue tax">IVA</th>
                    <th class="blue unit">PRECIO UNITARIO</th>
                    <th class="blue total">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
            @foreach($visibleItems as $item)
                <tr>
                    <td class="desc wrap">{{ $item->description }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="cost nowrap">{{ $money->format($item->unit_cost, $currency) }}</td>
                    <td class="tax nowrap">{{ $money->format($item->tax_amount, $currency) }}</td>
                    <td class="unit nowrap">{{ $money->format($item->unit_price, $currency) }}</td>
                    <td class="total nowrap">{{ $money->format($item->line_total, $currency) }}</td>
                </tr>
            @endforeach
            @for($i = $visibleItems->count(); $i < 6; $i++)
                <tr>
                    <td class="desc">&nbsp;</td>
                    <td class="qty">&nbsp;</td>
                    <td class="cost">&nbsp;</td>
                    <td class="tax">&nbsp;</td>
                    <td class="unit">&nbsp;</td>
                    <td class="total">&nbsp;</td>
                </tr>
            @endfor
            </tbody>
        </table>

        @if($isQuotation)
            <section class="middle-grid">
                <table class="quotation-bottom">
                    <tr>
                        <td class="blue-row warranty" colspan="2">{{ $warrantyText }}</td>
                    </tr>
                    <tr>
                        <td class="blue-row" colspan="2">OBSERVACIONES</td>
                    </tr>
                    <tr>
                        <td class="observations wrap" colspan="2">{{ $invoice->observations ?: ' ' }}</td>
                    </tr>
                    <tr>
                        <td class="advance" colspan="2">PAGA Y SE&Ntilde;AL EQUIPO Y MATERIALES AVANCE DE PAGO</td>
                    </tr>
                    <tr>
                        <td class="bank-label">CUENTA DE<br>BANCO</td>
                        <td class="bank-value">
                            @if($invoice->bankAccount)
                                {{ $invoice->bankAccount->bank_name }} - {{ $invoice->bankAccount->account_holder }}<br>
                                {{ $invoice->bankAccount->iban ?: $invoice->bankAccount->account_number ?: 'SIN NUMERO DE CUENTA' }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="service-guarantee" colspan="2">SOMOS TECNICOS HOMOLOGOS Y GARANTIZAMOS 100%<br>NUESTROS SERVICIOS.</td>
                    </tr>
                </table>

                <div class="quotation-side">
                    <table class="totals-table">
                        <tr>
                            <td class="label">VALIDEZ:</td>
                            <td class="right received nowrap">{{ $quotationValidUntil?->format('d/m/Y') ?: 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label">SUB-TOTAL:</td>
                            <td class="right nowrap">{{ $money->format($invoice->subtotal, $currency) }}</td>
                        </tr>
                        <tr>
                            <td class="label">IVA:</td>
                            <td class="right nowrap">{{ $money->format($invoice->tax_total, $currency) }}</td>
                        </tr>
                        <tr>
                            <td class="label grand-label">TOTAL ESTIMADO:</td>
                            <td class="right grand-value nowrap">{{ $money->format($invoice->total, $currency) }}</td>
                        </tr>
                    </table>

                    <div></div>

                    <table class="signature-table">
                        <tr>
                            <td class="blue signature-title">RECIBIDO POR</td>
                        </tr>
                        <tr>
                            <td class="signature-name">{{ $invoice->received_by ?: ' ' }}</td>
                        </tr>
                        <tr>
                            <td class="blue signature-title">PREPARADO POR</td>
                        </tr>
                        <tr>
                            <td class="prepared-name">{{ $invoice->prepared_by ?: ' ' }}</td>
                        </tr>
                    </table>
                </div>
            </section>
        @else
            <section class="middle-grid">
                <table class="notes-table">
                    <tr>
                        <td class="blue warranty-row">
                            {{ $warrantyText }}
                        </td>
                    </tr>
                    <tr>
                        <td class="blue observations-label">OBSERVACIONES</td>
                    </tr>
                    <tr>
                        <td class="observations-box wrap">{{ $invoice->observations ?: ' ' }}</td>
                    </tr>
                </table>

                <table class="totals-table">
                    <tr>
                        <td class="label">IMP. RECIBIDO</td>
                        <td class="right received nowrap">{{ $money->format($invoice->amount_received, $currency) }}</td>
                    </tr>
                    <tr>
                        <td class="label">SUB-TOTAL:</td>
                        <td class="right nowrap">{{ $money->format($invoice->subtotal, $currency) }}</td>
                    </tr>
                    <tr>
                        <td class="label">IVA:</td>
                        <td class="right nowrap">{{ $money->format($invoice->tax_total, $currency) }}</td>
                    </tr>
                    <tr>
                        <td class="label grand-label">TOTAL A PAGAR:</td>
                        <td class="right grand-value nowrap">{{ $money->format($invoice->total, $currency) }}</td>
                    </tr>
                    <tr>
                        <td class="label">BALANCE PENDIENTE:</td>
                        <td class="right nowrap">{{ $money->format($invoice->balance_due, $currency) }}</td>
                    </tr>
                </table>
            </section>
        @endif

        @if(! $isQuotation)
            <section class="bottom-grid">
                <table class="bank-table">
                    <tr>
                        <td class="blue bank-title">CUENTAS BANCARIAS</td>
                    </tr>
                    <tr>
                        <td class="bank-name">{{ $invoice->bankAccount?->bank_name ?: ' ' }}</td>
                    </tr>
                    <tr>
                        <td class="bank-details">
                            @if($invoice->bankAccount)
                                <div><strong>Tipo:</strong> {{ $invoice->bankAccount->account_type === 'unofficial' ? 'No oficial' : 'Oficial' }}</div>
                                <div><strong>Titular:</strong> {{ $invoice->bankAccount->account_holder }}</div>
                                @if($invoice->bankAccount->iban)
                                    <div><strong>IBAN:</strong> {{ $invoice->bankAccount->iban }}</div>
                                @endif
                                @if($invoice->bankAccount->account_number)
                                    <div><strong>No. cuenta:</strong> {{ $invoice->bankAccount->account_number }}</div>
                                @endif
                                @if($invoice->bankAccount->currency)
                                    <div><strong>Moneda:</strong> {{ $invoice->bankAccount->currency->code }}</div>
                                @endif
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="copy-badge">
                            ORIGINAL: CLIENTE<br>
                            COPIA: VENDEDOR
                        </td>
                    </tr>
                </table>

                <table class="signature-table">
                    <tr>
                        <td class="blue signature-title">RECIBIDO POR</td>
                    </tr>
                    <tr>
                        <td class="signature-name">{{ $invoice->received_by ?: ' ' }}</td>
                    </tr>
                    <tr>
                        <td class="blue signature-title">PREPARADO POR</td>
                    </tr>
                    <tr>
                        <td class="prepared-name">{{ $invoice->prepared_by ?: ' ' }}</td>
                    </tr>
                </table>
            </section>

            <table class="legal-table">
                <tr>
                    <td class="blue legal-title">CONFORMIDAD DEL CLIENTE</td>
                </tr>
                <tr>
                    <td class="legal-text wrap">{{ $legalText }}</td>
                </tr>
            </table>
        @endif

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
    </div>
</section>
</body>
</html>
