@php
    /** @var \App\Models\Invoice $invoice */
    $account = $invoice->bankAccount;
    $paymentMethod = $invoice->paymentMethodLabel();
    $isBankTransfer = str_contains($paymentMethod, 'Transferencia bancaria');
@endphp
<div class="note-box">
    <span class="note-ico"><x-pdf-icon name="landmark" :size="12" /></span>
    <div>
        <div class="note-title">Formas de pago</div>
        <div class="note-text">
            {{ $paymentMethod }}
            @if($isBankTransfer && $account?->bank_name)
                <br>{{ $account->bank_name }}
            @endif
            @if($isBankTransfer && $account?->account_holder)
                <br>Titular: {{ $account->account_holder }}
            @endif
            @if($isBankTransfer && $account?->iban)
                <br><span class="iban">{{ $account->iban }}</span>
            @elseif($isBankTransfer && $account?->account_number)
                <br><span class="iban">{{ $account->account_number }}</span>
            @endif
        </div>
    </div>
</div>
