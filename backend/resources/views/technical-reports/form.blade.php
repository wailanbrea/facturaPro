@php
    $isEdit = $report->exists;
    $selectedClientId = old('client_id', $report->client_id);
    $selectedProfileId = old('fiscal_profile_id', $report->fiscal_profile_id ?: $fiscalProfiles->firstWhere('is_default', true)?->id ?: $fiscalProfiles->first()?->id);
    $canDownloadReports = auth()->user()?->hasPermission('descargar_informes');
@endphp

<form id="technical-report-form" method="POST" action="{{ $action }}" class="form">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="invoice-grid">
        <section class="card form">
            <h3>Datos del informe</h3>
            <div class="fields">
                <div class="field">
                    <label>Numero</label>
                    <input name="report_number"
                           value="{{ old('report_number', $report->report_number) }}"
                           placeholder="{{ $reportSetting->previewNextNumber() }}"
                           @readonly(! $reportSetting->allow_manual_number)>
                    @if(! $reportSetting->allow_manual_number)
                        <p class="muted" style="font-size:12px;margin-top:6px">La numeracion se asigna automaticamente.</p>
                    @endif
                </div>
                <div class="field">
                    <label>Fecha</label>
                    <input name="report_date" type="date" value="{{ old('report_date', $report->report_date?->toDateString() ?? now()->toDateString()) }}" required>
                </div>
                <div class="field">
                    <label>Estado</label>
                    <select name="status">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $report->status ?? 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Perfil fiscal</label>
                    <select name="fiscal_profile_id" required>
                        @foreach($fiscalProfiles as $profile)
                            <option value="{{ $profile->id }}" @selected((int) $selectedProfileId === $profile->id)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <aside class="card">
            <h3>Acciones</h3>
            <p class="muted">El informe guarda copia del emisor, cliente y titulos al momento de crearse.</p>
            <div class="actions">
                <button class="btn primary" type="submit">{{ $submitLabel }}</button>
                @if(! $isEdit)
                    <button class="btn" type="submit" formaction="{{ route('web.technical-reports.preview-draft') }}" formtarget="_blank">Vista previa</button>
                @else
                    <a class="btn" href="{{ route('web.technical-reports.preview', $report) }}" target="_blank">Vista previa</a>
                    @if($canDownloadReports)
                        <button class="btn" type="submit" form="regenerate-report-pdf">Regenerar PDF</button>
                    @endif
                    <a class="btn" href="{{ route('web.technical-reports.show', $report) }}">Cancelar</a>
                @endif
            </div>
        </aside>
    </div>

    <section class="card form">
        <h3>Destinatario</h3>
        <div class="fields">
            <div class="field span-2">
                <label>Buscar cliente</label>
                <select name="client_id" data-client-select>
                    <option value="">Sin cliente vinculado</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                                data-name="{{ $client->name }}"
                                data-tax="{{ $client->tax_id }}"
                                data-address="{{ trim($client->address.' '.$client->city) }}"
                                @selected((int) $selectedClientId === $client->id)>
                            {{ $client->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Destinatario</label>
                <input name="recipient_name" data-recipient-name value="{{ old('recipient_name', $report->recipient_name) }}" required>
            </div>
            <div class="field">
                <label>Identificacion fiscal</label>
                <input name="recipient_tax_id" data-recipient-tax value="{{ old('recipient_tax_id', $report->recipient_tax_id) }}">
            </div>
            <div class="field span-2">
                <label>Direccion</label>
                <textarea name="recipient_address" data-recipient-address required>{{ old('recipient_address', $report->recipient_address) }}</textarea>
            </div>
        </div>
    </section>

    <section class="card form">
        <h3>Contenido tecnico</h3>
        <div class="fields">
            @foreach([1, 2, 3, 4] as $section)
                <div class="field span-2">
                    <label>Titulo seccion {{ $section }}</label>
                    <input name="section_{{ $section }}_title" value="{{ old('section_'.$section.'_title', $report->{'section_'.$section.'_title'}) }}" required>
                </div>
                <div class="field span-2">
                    <label>Contenido seccion {{ $section }}</label>
                    <textarea name="section_{{ $section }}_content" style="min-height:130px">{{ old('section_'.$section.'_content', $report->{'section_'.$section.'_content'}) }}</textarea>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card form">
        <h3>Textos adicionales</h3>
        <div class="fields">
            <div class="field span-2">
                <label>Texto introductorio</label>
                <textarea name="intro_text">{{ old('intro_text', $report->intro_text) }}</textarea>
            </div>
            <div class="field span-2">
                <label>Texto final</label>
                <textarea name="final_text">{{ old('final_text', $report->final_text) }}</textarea>
            </div>
            <div class="field span-2">
                <label>Observaciones internas</label>
                <textarea name="notes">{{ old('notes', $report->notes) }}</textarea>
            </div>
        </div>
    </section>
</form>

@if($isEdit)
    <form id="regenerate-report-pdf" method="POST" action="{{ route('web.technical-reports.generate-pdf', $report) }}">
        @csrf
    </form>
@endif

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const clientSelect = document.querySelector('[data-client-select]');
        const recipientName = document.querySelector('[data-recipient-name]');
        const recipientTax = document.querySelector('[data-recipient-tax]');
        const recipientAddress = document.querySelector('[data-recipient-address]');

        if (!clientSelect) return;

        clientSelect.addEventListener('change', () => {
            const option = clientSelect.selectedOptions[0];
            if (!option || !option.value) return;

            recipientName.value = option.dataset.name || '';
            recipientTax.value = option.dataset.tax || '';
            recipientAddress.value = option.dataset.address || '';
        });
    });
</script>
@endsection
