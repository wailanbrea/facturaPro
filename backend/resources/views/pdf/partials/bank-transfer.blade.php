@php
    /**
     * Formas de pago. Solo transferencia bancaria: efectivo, tarjeta y Bizum
     * son zona prohibida aunque aparezcan en la maqueta de referencia.
     *
     * @var \App\Models\Invoice $invoice
     */
    $account = $invoice->bankAccount;
@endphp
<div class="note-box">
    <span class="note-ico"><x-pdf-icon name="landmark" :size="12" /></span>
    <div>
        <div class="note-title">Formas de pago</div>
        <div class="note-text">
            Transferencia bancaria
            @if($account?->bank_name)
                <br>{{ $account->bank_name }}
            @endif
            @if($account?->account_holder)
                <br>Titular: {{ $account->account_holder }}
            @endif
            @if($account?->iban)
                <br><span class="iban">{{ $account->iban }}</span>
            @elseif($account?->account_number)
                <br><span class="iban">{{ $account->account_number }}</span>
            @endif
        </div>
    </div>
</div>
