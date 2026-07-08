@php
    $logoSrc = null;
    $logoPathValue = $report->seller_logo_path
        ?: $report->fiscalProfile?->logo_path
        ?: 'logos/logo_tu_tecnico_autorizado.png';

    if ($logoPathValue) {
        $logoPath = storage_path('app/public/'.$logoPathValue);
        if (is_file($logoPath)) {
            $mime = function_exists('mime_content_type') ? mime_content_type($logoPath) : 'image/png';
            $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
        }
    }

    // Solo se imprimen las secciones con contenido; las vacías no dejan huecos.
    $sections = collect([
        [$report->section_1_title, $report->section_1_content],
        [$report->section_2_title, $report->section_2_content],
        [$report->section_3_title, $report->section_3_content],
        [$report->section_4_title, $report->section_4_content],
    ])->filter(fn (array $section): bool => filled($section[0]) || filled($section[1]));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report->report_number }} - INFORME</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #000000;
            font-family: Tahoma, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.2;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 49mm 30mm 15mm;
            background: #ffffff;
        }
        .page.with-logo {
            padding-top: 15mm;
        }
        .logo {
            margin: 0 0 10pt;
            text-align: center;
        }
        .logo img {
            display: inline-block;
            max-width: 125mm;
            max-height: 24mm;
            object-fit: contain;
        }
        .issuer {
            margin: 0 0 16pt;
            text-align: center;
            font-family: Calibri, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .issuer div {
            margin: 0 0 7pt;
        }
        .with-logo .issuer {
            margin-bottom: 12pt;
        }
        .with-logo .issuer div {
            margin-bottom: 5pt;
        }
        .meta {
            margin: 0 0 15pt;
            font-family: Calibri, Arial, Helvetica, sans-serif;
            font-size: 11pt;
        }
        .meta-row {
            margin-bottom: 8pt;
        }
        .meta-row.date {
            text-align: right;
            margin-bottom: 8pt;
        }
        .label {
            display: inline-block;
            margin-right: 4pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .date .label {
            min-width: 0;
            margin-right: 5pt;
        }
        h1 {
            margin: 18pt 0 6pt;
            text-align: center;
            font-family: Tahoma, Arial, Helvetica, sans-serif;
            font-size: 20pt;
            font-weight: 400;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .intro,
        .final {
            margin: 0 0 16pt;
            white-space: pre-line;
            text-align: left;
        }
        .section {
            margin-top: 20pt;
            page-break-inside: avoid;
        }
        .section:first-of-type {
            margin-top: 9pt;
        }
        .section-title {
            margin: 0 0 14pt;
            font-family: Tahoma, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            font-weight: 700;
        }
        .section-content {
            margin: 0;
            font-family: Tahoma, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.2;
            white-space: pre-line;
            text-align: left;
        }
        .final {
            margin-top: 20pt;
        }
        .footer {
            display: none;
        }
    </style>
</head>
<body>
    <main class="page {{ $logoSrc ? 'with-logo' : 'without-logo' }}">
        @if($logoSrc)
            <div class="logo">
                <img src="{{ $logoSrc }}" alt="Logo">
            </div>
        @endif

        <header class="issuer">
            <div>{{ $report->seller_name }}</div>
            @if($report->seller_tax_id)
                <div>{{ $report->seller_tax_id }}</div>
            @endif
            <div>{{ trim($report->seller_address.' - '.$report->seller_city, ' -') }}</div>
        </header>

        <section class="meta">
            <div class="meta-row date"><span class="label">FECHA:</span>{{ $report->report_date?->format('d/m/Y') }}</div>
            <div class="meta-row"><span class="label">DESTINATARIO:</span>{{ $report->recipient_name }}</div>
            @if($report->recipient_tax_id)
                <div class="meta-row"><span class="label">CIF/RNC/NIF:</span>{{ $report->recipient_tax_id }}</div>
            @endif
            <div class="meta-row"><span class="label">DIRECCION:</span>{{ $report->recipient_address }}</div>
        </section>

        <h1>INFORME</h1>

        @if($report->intro_text)
            <p class="intro">{{ $report->intro_text }}</p>
        @endif

        @foreach($sections as [$title, $content])
            <section class="section">
                <h2 class="section-title">{{ $title }}</h2>
                <p class="section-content">{{ $content }}</p>
            </section>
        @endforeach

        @if($report->final_text)
            <p class="final">{{ $report->final_text }}</p>
        @endif

        <footer class="footer">
            Informe {{ $report->report_number }} generado por FacturaPro.
        </footer>
    </main>
</body>
</html>
