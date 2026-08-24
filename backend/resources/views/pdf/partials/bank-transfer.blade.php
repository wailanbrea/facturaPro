@php
    /** @var \App\Models\Invoice $invoice */
    $account = $invoice->bankAccount;
    $paymentMethod = $invoice->paymentMethodLabel();
    $paymentLines = [];

    if ($paymentMethod !== '') {
        $paymentLines[] = $paymentMethod;
    } elseif ($account) {
        $paymentLines[] = 'Transferencia bancaria';
    }

    if ($account?->bank_name) {
        $paymentLines[] = $account->bank_name;
    }
    if ($account?->account_holder) {
        $paymentLines[] = 'Titular: '. $account->account_holder;
    }
    if ($account?->iban) {
        $paymentLines[] = $account->iban;
    } elseif ($account?->account_number) {
        $paymentLines[] = $account->account_number;
    }
@endphp
<div class="note-box">
    <span class="note-ico"><x-pdf-icon name="landmark" :size="12" /></span>
    <div>
        <div class="note-title">Formas de pago</div>
        <div class="note-text">{!! implode('<br>', array_map('e', $paymentLines)) !!}</div>
    </div>
</div>