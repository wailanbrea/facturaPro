<div class="legal-band">CONDICIONES GENERALES DE PRESTACION DEL SERVICIO</div>

@include('pdf.partials.legal-grid', ['blocks' => $legalBlocks])

@if($ctx->verificationCode)
    <div class="muted" style="margin-top:4mm">
        Documento emitido y autenticado por el sistema. C&oacute;digo de seguridad:
        <span class="verify-code">{{ $ctx->verificationCode }}</span>
    </div>
@endif

@include('pdf.partials.eco-notice')
