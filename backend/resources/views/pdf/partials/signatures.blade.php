@php
    /**
     * Espacio de firma. Se deja en blanco a proposito para firmar a mano o
     * estampar el sello; cuando el cliente aporte la firma digitalizada podra
     * incrustarse aqui como data URI.
     *
     * @var string      $leftRole
     * @var string|null $leftName
     * @var string      $rightRole
     * @var string|null $rightName
     */
@endphp
<div class="sign-row">
    <div class="sign-slot">
        <div class="sign-space"></div>
        <div class="sign-line">
            <div class="sign-name">{{ $leftName ?: ' ' }}</div>
            <div class="sign-role">{{ $leftRole }}</div>
        </div>
    </div>
    <div class="sign-slot">
        <div class="sign-space"></div>
        <div class="sign-line">
            <div class="sign-name">{{ $rightName ?: ' ' }}</div>
            <div class="sign-role">{{ $rightRole }}</div>
        </div>
    </div>
</div>
