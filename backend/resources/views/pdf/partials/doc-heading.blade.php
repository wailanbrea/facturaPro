@php
    /**
     * Cabecera comun a factura y presupuesto: logo, sellos de confianza,
     * titulo, numero de documento y QR de verificacion.
     *
     * @var string|null $logoSrc     Data URI del logo (nunca una URL remota).
     * @var string      $sellerInitial
     * @var string      $title
     * @var bool        $titleTwoLine
     * @var string      $numberLabel
     * @var string      $numberValue
     * @var string|null $qrSrc       Data URI del QR, solo si el documento esta firmado.
     * @var string      $qrTitle
     * @var string      $qrText
     * @var int         $pageNo
     * @var int         $totalPages
     */
    $titleTwoLine = $titleTwoLine ?? false;
    $qrSrc = $qrSrc ?? null;
@endphp
<div class="page-flag">PÁGINA {{ $pageNo }} DE {{ $totalPages }}</div>

<header class="doc-head">
    <div class="logo-box">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="">
        @else
            <div class="logo-initial">{{ $sellerInitial }}</div>
        @endif
    </div>

    <div class="trust-badges">
        <div class="badge">
            <x-pdf-icon name="shield-check" :size="13" />
            <span class="badge-text">Intervenciones<br>garantizadas</span>
        </div>
        <div class="badge">
            <x-pdf-icon name="award" :size="13" />
            <span class="badge-text">Técnicos<br>cualificados</span>
        </div>
        <div class="badge">
            <x-pdf-icon name="package" :size="13" />
            <span class="badge-text">Repuestos<br>originales</span>
        </div>
        <div class="badge">
            <x-pdf-icon name="clock" :size="13" />
            <span class="badge-text">Atención<br>24/7</span>
        </div>
    </div>

    <div>
        <div class="doc-title {{ $titleTwoLine ? 'two-line' : '' }}">{!! $title !!}</div>
        <div class="doc-number-row">
            <div class="doc-number">
                <div class="doc-number-label">{{ $numberLabel }}</div>
                <div class="doc-number-value">{{ $numberValue }}</div>
            </div>
            <div class="qr-block">
                @if($qrSrc)
                    <img src="{{ $qrSrc }}" alt="">
                @endif
            </div>
        </div>
        @if($qrSrc)
            <div class="qr-caption right" style="margin-top:1.4mm">
                {{ $qrTitle }}
                <span>{{ $qrText }}</span>
            </div>
        @endif
    </div>
</header>
