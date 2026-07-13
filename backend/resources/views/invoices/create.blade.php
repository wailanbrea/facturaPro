@extends('layouts.app')

@php
    $isEdit = $invoice->exists;
    $oldItems = old('items');
    $rows = $oldItems ? collect($oldItems)->map(fn ($item) => (object) $item) : $items;
    $selectedClientId = old('client_id', $invoice->client_id);
    $amountReceivedValue = number_format((float) old('amount_received', $invoice->amount_received ?? 0), 2, '.', '');
    $legalTextsEditable = old('edit_legal_texts') === '1';
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
                    <label>Cliente existente</label>
                    <select name="client_id" id="client-select">
                        <option value="">Nuevo cliente en esta factura</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                data-name="{{ e($client->name) }}"
                                data-tax-id="{{ e($client->tax_id ?? '') }}"
                                data-address="{{ e($client->address ?? '') }}"
                                data-city="{{ e($client->city ?? '') }}"
                                data-phone="{{ e($client->phone ?? '') }}"
                                data-email="{{ e($client->email ?? '') }}"
                                @selected((int) $selectedClientId === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nombre del cliente</label>
                    <input name="client_name" id="client-name-input" value="{{ old('client_name', $invoice->client_name) }}" maxlength="255">
                </div>
                <div class="field">
                    <label>RNC / cedula</label>
                    <input name="client_tax_id" id="client-tax-id-input" value="{{ old('client_tax_id', $invoice->client_tax_id) }}" maxlength="255">
                </div>
                <div class="field">
                    <label>Telefono</label>
                    <input name="client_phone" id="client-phone-input" value="{{ old('client_phone') }}" maxlength="255">
                </div>
                <div class="field">
                    <label>Correo</label>
                    <input name="client_email" id="client-email-input" type="email" value="{{ old('client_email') }}" maxlength="255">
                </div>
                <div class="field">
                    <label>Ciudad</label>
                    <input name="client_city" id="client-city-input" value="{{ old('client_city') }}" maxlength="255">
                </div>
                <div class="field span-2">
                    <label>Direccion</label>
                    <input name="client_address" id="client-address-input" value="{{ old('client_address', $invoice->client_address) }}" maxlength="255">
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
                                data-next-invoice="{{ $numberPreviews[$profile->id]['invoice'] ?? '' }}"
                                data-next-quotation="{{ $numberPreviews[$profile->id]['quotation'] ?? '' }}"
                                @selected((int) old('fiscal_profile_id', $invoice->fiscal_profile_id) === $profile->id)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[12px] text-on-surface-variant mt-1">La numeracion es continua por perfil fiscal y logo.</p>
                </div>
                <div class="field">
                    <label>Proximo numero</label>
                    <input id="invoice-number-preview" value="{{ $invoice->invoice_number ?? '' }}" readonly>
                    <p class="text-[12px] text-on-surface-variant mt-1">Cambia automaticamente segun tipo, perfil y logo.</p>
                </div>
                <div class="field span-2" id="logo-picker-field">
                    <label>Logo en factura <span class="text-on-surface-variant font-normal">(por defecto el de la empresa)</span></label>
                    @php $selectedLogo = old('logo_path', $invoice->logo_path ?? ''); @endphp
                    <div class="flex flex-wrap gap-3 mt-1">
                        @foreach($availableLogos as $logo)
                        @php
                            $logoPreview = $numberPreviews[$logo->fiscal_profile_id]['logos'][$logo->path] ?? [];
                        @endphp
                        <label data-logo-option="logo"
                            data-profile-id="{{ $logo->fiscal_profile_id }}"
                            data-next-invoice="{{ $logoPreview['invoice'] ?? '' }}"
                            data-next-quotation="{{ $logoPreview['quotation'] ?? '' }}"
                            class="flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-2 text-[13px] {{ $selectedLogo === $logo->path ? 'border-primary bg-primary-soft-2' : 'border-outline-variant hover:bg-surface-low' }}" id="logo-opt-{{ $loop->index }}">
                            <input type="radio" name="logo_path" value="{{ $logo->path }}" class="sr-only logo-radio" {{ $selectedLogo === $logo->path ? 'checked' : '' }}>
                            <img src="{{ asset('storage/'.$logo->path) }}" alt="{{ $logo->label ?? basename($logo->path) }}" class="w-10 h-8 object-contain rounded">
                            <span class="max-w-[140px] truncate">{{ $logo->label ?? basename($logo->path) }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p id="profile-logos-empty" class="text-[13px] text-on-surface-variant mt-1" style="display:none">Este perfil no tiene logos cargados. Puedes continuar sin logo o cargar logos en Configuracion > Perfiles fiscales.</p>
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
                <div class="field" id="amount-received-field"><label>Importe recibido</label><input id="amount-received-input" name="amount_received" type="number" step="0.01" min="0" value="{{ $amountReceivedValue }}"></div>
                @php $lockedFields = $lockedFields ?? []; @endphp
                <div class="field span-2" id="legal-texts-lock-panel">
                    <input type="hidden" id="edit-legal-texts-input" name="edit_legal_texts" value="{{ $legalTextsEditable ? '1' : '0' }}">
                    <div class="actions" style="justify-content:space-between;align-items:center;margin:0">
                        <div>
                            <strong>Textos de factura</strong>
                            <p class="muted" style="margin:4px 0 0;font-size:12px">Texto legal y texto de conformidad estan bloqueados por defecto.</p>
                        </div>
                        <button class="btn" id="enable-legal-texts-btn" type="button">{{ $legalTextsEditable ? 'Edicion habilitada' : 'Habilitar edicion' }}</button>
                    </div>
                </div>
                <div class="field span-2">
                    <label>Texto de conformidad
                        @if(in_array('conformity_text', $lockedFields, true))
                            <span class="text-on-surface-variant font-normal">🔒 Bloqueado por administración</span>
                        @endif
                    </label>
                    <textarea name="conformity_text" data-legal-text-field @readonly(! $legalTextsEditable) @if(! $legalTextsEditable) style="background:#f3f2fe;cursor:not-allowed" @endif>{{ old('conformity_text', $invoice->conformity_text) }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Texto legal
                        @if(in_array('legal_text', $lockedFields, true))
                            <span class="text-on-surface-variant font-normal">🔒 Bloqueado por administración</span>
                        @endif
                    </label>
                    <textarea name="legal_text" data-legal-text-field @readonly(! $legalTextsEditable) @if(! $legalTextsEditable) style="background:#f3f2fe;cursor:not-allowed" @endif>{{ old('legal_text', $invoice->legal_text) }}</textarea>
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
        amountInput.value = '0.00';
    }

    window.refreshInvoiceNumberPreview?.();
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
document.querySelectorAll('input[name="logo_path"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('[data-logo-option]').forEach(el => {
            el.classList.remove('border-primary', 'bg-primary-soft-2');
            el.classList.add('border-outline-variant');
        });
        if (radio.checked) {
            radio.closest('label').classList.add('border-primary', 'bg-primary-soft-2');
            radio.closest('label').classList.remove('border-outline-variant');
        }
        window.refreshInvoiceNumberPreview?.();
    });
});

document.getElementById('document-type-select')?.addEventListener('change', syncDocumentTypeFields);
syncDocumentTypeFields();

(function () {
    const unlockInput = document.getElementById('edit-legal-texts-input');
    const unlockButton = document.getElementById('enable-legal-texts-btn');
    const fields = Array.from(document.querySelectorAll('[data-legal-text-field]'));

    function setLegalTextsEditable(enabled) {
        if (unlockInput) {
            unlockInput.value = enabled ? '1' : '0';
        }

        fields.forEach(field => {
            field.readOnly = !enabled;
            field.style.background = enabled ? '' : '#f3f2fe';
            field.style.cursor = enabled ? '' : 'not-allowed';
        });

        document.querySelectorAll('.legal-lock-label').forEach(label => {
            label.textContent = enabled ? 'Edicion habilitada' : 'Bloqueado';
        });

        if (unlockButton) {
            unlockButton.textContent = enabled ? 'Edicion habilitada' : 'Habilitar edicion';
            unlockButton.disabled = enabled;
        }
    }

    unlockButton?.addEventListener('click', () => setLegalTextsEditable(true));
    setLegalTextsEditable(unlockInput?.value === '1');
})();

(function () {
    const select = document.getElementById('client-select');
    const fields = {
        name: document.getElementById('client-name-input'),
        taxId: document.getElementById('client-tax-id-input'),
        address: document.getElementById('client-address-input'),
        city: document.getElementById('client-city-input'),
        phone: document.getElementById('client-phone-input'),
        email: document.getElementById('client-email-input'),
    };
    if (!select || !fields.name) return;

    function syncClientFields(overwrite) {
        const option = select.selectedOptions[0];
        const hasClient = option && option.value !== '';

        fields.name.required = !hasClient;

        if (!hasClient || !overwrite) {
            return;
        }

        fields.name.value = option.dataset.name || '';
        fields.taxId.value = option.dataset.taxId || '';
        fields.address.value = option.dataset.address || '';
        fields.city.value = option.dataset.city || '';
        fields.phone.value = option.dataset.phone || '';
        fields.email.value = option.dataset.email || '';
    }

    select.addEventListener('change', () => syncClientFields(true));
    syncClientFields(false);
})();

(function () {
    const profileSelect = document.getElementById('fiscal-profile-select');
    const bankSelect = document.getElementById('bank-account-select');
    const logoEmptyMessage = document.getElementById('profile-logos-empty');
    const documentTypeSelect = document.getElementById('document-type-select');
    const numberPreview = document.getElementById('invoice-number-preview');
    if (!profileSelect) return;

    function pickBankFor(profileId) {
        if (!bankSelect) return '';
        const options = Array.from(bankSelect.options).filter(o => o.value !== '');
        const matches = options.filter(o => (o.dataset.fiscalProfileId || '') === String(profileId || ''));
        if (matches.length === 0) return '';
        const def = matches.find(o => o.dataset.isDefault === '1');
        return (def || matches[0]).value;
    }

    function visibleLogoRadioFor(value) {
        return Array.from(document.querySelectorAll('input[name="logo_path"]'))
            .find(radio => radio.value === (value || '') && radio.closest('[data-logo-option]')?.style.display !== 'none');
    }

    function firstVisibleLogoRadio() {
        return Array.from(document.querySelectorAll('[data-logo-option="logo"] input[name="logo_path"]'))
            .find(radio => radio.closest('[data-logo-option]')?.style.display !== 'none');
    }

    function refreshLogoOptions() {
        const profileId = String(profileSelect.value || '');
        let visibleLogos = 0;

        document.querySelectorAll('[data-logo-option]').forEach(option => {
            const matchesProfile = option.dataset.profileId === profileId;
            option.style.display = matchesProfile ? '' : 'none';

            if (matchesProfile) {
                visibleLogos++;
            }
        });

        const checked = document.querySelector('input[name="logo_path"]:checked');
        const checkedOption = checked?.closest('[data-logo-option]');
        if (checked && checked.value !== '' && checkedOption?.style.display === 'none') {
            checked.checked = false;
        }

        if (!document.querySelector('input[name="logo_path"]:checked')) {
            const selectedProfile = profileSelect.selectedOptions[0];
            const defaultLogo = selectedProfile ? selectedProfile.dataset.logo : '';
            const radio = visibleLogoRadioFor(defaultLogo) || firstVisibleLogoRadio();

            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }

        if (logoEmptyMessage) {
            logoEmptyMessage.style.display = visibleLogos === 0 ? '' : 'none';
        }
    }

    window.refreshInvoiceNumberPreview = function () {
        if (!numberPreview) return;

        const option = profileSelect.selectedOptions[0];
        const type = documentTypeSelect?.value === 'quotation' ? 'quotation' : 'invoice';
        const checkedLogo = document.querySelector('input[name="logo_path"]:checked');
        const checkedLogoOption = checkedLogo?.closest('[data-logo-option]');
        const logoPreview = checkedLogoOption?.dataset.logoOption === 'logo'
            ? (type === 'quotation' ? checkedLogoOption.dataset.nextQuotation : checkedLogoOption.dataset.nextInvoice)
            : '';

        numberPreview.value = logoPreview || (option ? (type === 'quotation' ? option.dataset.nextQuotation : option.dataset.nextInvoice) || '' : '');
    };

    profileSelect.addEventListener('change', () => {
        if (bankSelect) {
            bankSelect.value = pickBankFor(profileSelect.value);
        }
        refreshLogoOptions();
        window.refreshInvoiceNumberPreview();
    });

    refreshLogoOptions();
    window.refreshInvoiceNumberPreview();
})();
</script>
@endsection
