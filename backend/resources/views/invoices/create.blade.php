@extends('layouts.app')

@php
    $isEdit = $invoice->exists;
    $oldItems = old('items');
    $rows = $oldItems ? collect($oldItems)->map(fn ($item) => (object) $item) : $items;
@endphp

@section('title', $isEdit ? 'Editar factura' : 'Nueva factura')
@section('subtitle', $isEdit ? 'Solo borradores pueden editarse' : 'Crear borrador con calculo backend')

@section('content')
<form method="POST" action="{{ $action }}" class="form">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <div class="invoice-grid">
        <section class="card form">
            <h3>Datos principales</h3>
            <div class="fields">
                <div class="field">
                    <label>Tipo de documento</label>
                    <select name="document_type" id="document-type-select" required>
                        <option value="invoice" @selected(old('document_type', $invoice->document_type ?? 'invoice') === 'invoice')>Factura</option>
                        <option value="quotation" @selected(old('document_type', $invoice->document_type ?? 'invoice') === 'quotation')>Presupuesto</option>
                    </select>
                </div>
                <div class="field">
                    <label>Cliente</label>
                    <select name="client_id" required>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $invoice->client_id) === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Fecha</label><input name="invoice_date" type="date" value="{{ old('invoice_date', $invoice->invoice_date?->toDateString() ?? now()->toDateString()) }}" required></div>
                <div class="field">
                    <label>Termino</label>
                    <select name="payment_term_id" required>
                        @foreach($paymentTerms as $term)
                            <option value="{{ $term->id }}" @selected((int) old('payment_term_id', $invoice->payment_term_id) === $term->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Moneda</label>
                    <select name="currency_id" required>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}" @selected((int) old('currency_id', $invoice->currency_id) === $currency->id)>{{ $currency->code }} ({{ $currency->symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Empresa <span class="text-error">*</span></label>
                    <select name="fiscal_profile_id" id="fiscal-profile-select" required>
                        <option value="" disabled @selected(old('fiscal_profile_id', $invoice->fiscal_profile_id) === null)>Selecciona una empresa</option>
                        @foreach($fiscalProfiles as $profile)
                            <option value="{{ $profile->id }}"
                                data-logo="{{ $profile->logo_path ?? '' }}"
                                @selected((int) old('fiscal_profile_id', $invoice->fiscal_profile_id) === $profile->id)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[12px] text-on-surface-variant mt-1">La numeración es continua por empresa y por usuario que factura.</p>
                </div>
                <div class="field span-2" id="logo-picker-field">
                    <label>Logo en factura <span class="text-on-surface-variant font-normal">(por defecto el de la empresa)</span></label>
                    @php $selectedLogo = old('logo_path', $invoice->logo_path ?? ''); @endphp
                    @if(count($availableLogos) > 0)
                    <div class="flex flex-wrap gap-3 mt-1">
                        <label class="flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-2 text-[13px] {{ $selectedLogo === '' ? 'border-primary bg-primary-soft-2' : 'border-outline-variant hover:bg-surface-low' }}">
                            <input type="radio" name="logo_path" value="" class="sr-only" {{ $selectedLogo === '' ? 'checked' : '' }}>
                            <span class="w-8 h-8 rounded bg-surface-mid flex items-center justify-center text-[10px] font-bold text-on-surface-variant">SIN</span>
                            <span>Sin logo</span>
                        </label>
                        @foreach($availableLogos as $path => $filename)
                        <label class="flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-2 text-[13px] {{ $selectedLogo === $path ? 'border-primary bg-primary-soft-2' : 'border-outline-variant hover:bg-surface-low' }}" id="logo-opt-{{ $loop->index }}">
                            <input type="radio" name="logo_path" value="{{ $path }}" class="sr-only logo-radio" {{ $selectedLogo === $path ? 'checked' : '' }}>
                            <img src="{{ asset('storage/'.$path) }}" alt="{{ $filename }}" class="w-10 h-8 object-contain rounded">
                            <span class="max-w-[140px] truncate">{{ $filename }}</span>
                        </label>
                        @endforeach
                    </div>
                    @else
                    <p class="text-[13px] text-on-surface-variant mt-1">No hay logos cargados aún. Sube logos a <code>storage/app/public/logos/</code></p>
                    @endif
                </div>
                <div class="field">
                    <label>Cuenta bancaria</label>
                    <select name="bank_account_id" id="bank-account-select">
                        <option value="">Sin cuenta</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}"
                                data-fiscal-profile-id="{{ $account->fiscal_profile_id ?? '' }}"
                                data-is-default="{{ $account->is_default ? '1' : '0' }}"
                                @selected((int) old('bank_account_id', $invoice->bank_account_id) === $account->id)>{{ $account->label }} - {{ $account->bank_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Garantia</label>
                    <select name="warranty_id" required>
                        @foreach($warranties as $warranty)
                            <option value="{{ $warranty->id }}" @selected((int) old('warranty_id', $invoice->warranty_id) === $warranty->id)>{{ $warranty->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" id="amount-received-field"><label>Importe recibido</label><input id="amount-received-input" name="amount_received" type="number" step="0.01" min="0" value="{{ old('amount_received', $invoice->amount_received ?? 0) }}"></div>
                @php $lockedFields = $lockedFields ?? []; @endphp
                <div class="field span-2">
                    <label>Texto de conformidad
                        @if(in_array('conformity_text', $lockedFields, true))
                            <span class="text-on-surface-variant font-normal">🔒 Bloqueado por administración</span>
                        @endif
                    </label>
                    <textarea name="conformity_text" @readonly(in_array('conformity_text', $lockedFields, true)) @if(in_array('conformity_text', $lockedFields, true)) style="background:#f3f2fe;cursor:not-allowed" @endif>{{ old('conformity_text', $invoice->conformity_text) }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Texto legal
                        @if(in_array('legal_text', $lockedFields, true))
                            <span class="text-on-surface-variant font-normal">🔒 Bloqueado por administración</span>
                        @endif
                    </label>
                    <textarea name="legal_text" @readonly(in_array('legal_text', $lockedFields, true)) @if(in_array('legal_text', $lockedFields, true)) style="background:#f3f2fe;cursor:not-allowed" @endif>{{ old('legal_text', $invoice->legal_text) }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Observaciones
                        @if(in_array('observations', $lockedFields, true))
                            <span class="text-on-surface-variant font-normal">🔒 Bloqueado por administración</span>
                        @endif
                    </label>
                    <textarea name="observations" @readonly(in_array('observations', $lockedFields, true)) @if(in_array('observations', $lockedFields, true)) style="background:#f3f2fe;cursor:not-allowed" @endif>{{ old('observations', $invoice->observations) }}</textarea>
                </div>
            </div>
        </section>
        <aside class="card">
            <h3>Acciones</h3>
            <p class="muted">Los totales se recalculan en backend. Cualquier subtotal enviado desde el navegador se ignora.</p>
            <button class="btn primary" type="submit">{{ $submitLabel }}</button>
            @if($isEdit)
                <a class="btn" href="{{ route('web.invoices.show', $invoice) }}">Cancelar</a>
            @endif
        </aside>
    </div>
    <section class="card">
        <h3>Productos / servicios</h3>
        <div id="items">
            @foreach($rows as $index => $item)
                <div class="line-row">
                    <div class="field"><label>Descripcion</label><input name="items[{{ $index }}][description]" value="{{ $item->description ?? '' }}" required></div>
                    <div class="field"><label>Cantidad</label><input name="items[{{ $index }}][quantity]" type="number" step="0.0001" min="0.0001" value="{{ $item->quantity ?? 1 }}" required></div>
                    <div class="field"><label>Costo unitario</label><input name="items[{{ $index }}][unit_cost]" type="number" step="0.0001" min="0" value="{{ $item->unit_cost ?? 0 }}" required></div>
                    <div class="field">
                        <label>Impuesto</label>
                        <select name="items[{{ $index }}][tax_id]" required>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}" @selected((int) ($item->tax_id ?? 0) === $tax->id)>{{ $tax->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn {{ $index === 0 ? '' : 'danger' }}" type="button" onclick="{{ $index === 0 ? 'addItem()' : 'this.parentElement.remove()' }}">{{ $index === 0 ? '+' : 'x' }}</button>
                </div>
            @endforeach
        </div>
    </section>
</form>
<script>
let itemIndex = {{ max(1, $rows->count()) }};
function syncDocumentTypeFields(){
    const documentType = document.getElementById('document-type-select');
    const amountField = document.getElementById('amount-received-field');
    const amountInput = document.getElementById('amount-received-input');
    const isQuotation = documentType && documentType.value === 'quotation';

    if (!amountField || !amountInput) {
        return;
    }

    amountField.style.display = isQuotation ? 'none' : '';
    amountInput.disabled = isQuotation;
    if (isQuotation) {
        amountInput.value = '0';
    }
}

function addItem(){
    const taxes = `{!! $taxes->map(fn($t) => '<option value="'.$t->id.'">'.e($t->name).'</option>')->implode('') !!}`;
    document.getElementById('items').insertAdjacentHTML('beforeend', `
    <div class="line-row">
        <div class="field"><input name="items[${itemIndex}][description]" required></div>
        <div class="field"><input name="items[${itemIndex}][quantity]" type="number" step="0.0001" min="0.0001" value="1" required></div>
        <div class="field"><input name="items[${itemIndex}][unit_cost]" type="number" step="0.0001" min="0" value="0" required></div>
        <div class="field"><select name="items[${itemIndex}][tax_id]" required>${taxes}</select></div>
        <button class="btn danger" type="button" onclick="this.parentElement.remove()">x</button>
    </div>`);
    itemIndex++;
}

// Logo picker highlight
document.querySelectorAll('.logo-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('[id^="logo-opt-"]').forEach(el => {
            el.classList.remove('border-primary', 'bg-primary-soft-2');
            el.classList.add('border-outline-variant');
        });
        if (radio.checked) {
            radio.closest('label').classList.add('border-primary', 'bg-primary-soft-2');
            radio.closest('label').classList.remove('border-outline-variant');
        }
    });
});

document.getElementById('document-type-select')?.addEventListener('change', syncDocumentTypeFields);
syncDocumentTypeFields();

(function () {
    const profileSelect = document.getElementById('fiscal-profile-select');
    const bankSelect = document.getElementById('bank-account-select');
    if (!profileSelect) return;

    function pickBankFor(profileId) {
        if (!bankSelect) return '';
        const options = Array.from(bankSelect.options).filter(o => o.value !== '');
        const matches = options.filter(o => (o.dataset.fiscalProfileId || '') === String(profileId || ''));
        if (matches.length === 0) return '';
        const def = matches.find(o => o.dataset.isDefault === '1');
        return (def || matches[0]).value;
    }

    // Select the logo that belongs to the chosen company (when it is among the
    // available logos). The user can still override it manually afterwards.
    function pickLogoFor(profileLogo) {
        const radio = Array.from(document.querySelectorAll('input[name="logo_path"]'))
            .find(r => r.value === (profileLogo || ''));
        if (radio && !radio.checked) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    }

    profileSelect.addEventListener('change', () => {
        if (bankSelect) {
            bankSelect.value = pickBankFor(profileSelect.value);
        }
        const opt = profileSelect.selectedOptions[0];
        pickLogoFor(opt ? opt.dataset.logo : '');
    });
})();
</script>
@endsection
