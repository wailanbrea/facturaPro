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
                           readonly>
                    <p class="muted" style="font-size:12px;margin-top:6px">La numeracion se asigna automaticamente.</p>
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
                    <select name="fiscal_profile_id" id="report-fiscal-profile-select" required>
                        @foreach($fiscalProfiles as $profile)
                            <option value="{{ $profile->id }}" @selected((int) $selectedProfileId === $profile->id)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field span-2">
                    <label>Logo del informe <span class="text-on-surface-variant font-normal">(centrado en la parte superior del documento)</span></label>
                    @php $selectedLogo = old('logo_path', $report->seller_logo_path ?? ''); @endphp
                    <div class="flex flex-wrap gap-3 mt-1">
                        @foreach($availableLogos as $logo)
                        <label data-report-logo-option="logo" data-profile-id="{{ $logo->fiscal_profile_id }}" class="flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-2 text-[13px] {{ $selectedLogo === $logo->path ? 'border-primary bg-primary-soft-2' : 'border-outline-variant hover:bg-surface-low' }} report-logo-opt">
                            <input type="radio" name="logo_path" value="{{ $logo->path }}" class="sr-only report-logo-radio" {{ $selectedLogo === $logo->path ? 'checked' : '' }}>
                            <img src="{{ asset('storage/'.$logo->path) }}" alt="{{ $logo->label ?? basename($logo->path) }}" class="w-10 h-8 object-contain rounded">
                            <span class="max-w-[140px] truncate">{{ $logo->label ?? basename($logo->path) }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p id="report-profile-logos-empty" class="text-[13px] text-on-surface-variant mt-1" style="display:none">Este perfil no tiene logos cargados. Carga un logo en Configuracion > Perfiles fiscales.</p>
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
        <div class="flex items-center justify-between">
            <h3 style="margin:0">Contenido del informe</h3>
            <button type="button" id="add-section-btn" class="btn" style="padding:6px 12px;font-size:12px">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Agregar seccion
            </button>
        </div>
        <p class="muted" style="font-size:12px;margin:8px 0 0">
            Empieza con una seccion (Titulo y Texto). Si necesitas mas, pulsa "Agregar seccion" (hasta 4).
        </p>
        @php
            $sectionLabels = [1 => ['Titulo', 'Texto'], 2 => ['2do Titulo', '2do Texto'], 3 => ['3er Titulo', '3er Texto'], 4 => ['4to Titulo', '4to Texto']];
        @endphp
        <div class="fields" id="report-sections">
            @foreach([1, 2, 3, 4] as $section)
                @php
                    $titleValue = old('section_'.$section.'_title', $report->{'section_'.$section.'_title'});
                    $contentValue = old('section_'.$section.'_content', $report->{'section_'.$section.'_content'});
                    $visible = $section === 1 || filled($titleValue) || filled($contentValue);
                @endphp
                <div class="field span-2 report-section" data-section="{{ $section }}" style="{{ $visible ? '' : 'display:none' }}">
                    <div class="flex items-center justify-between mb-1">
                        <label style="margin:0">{{ $sectionLabels[$section][0] }} @if($section === 1)<span class="text-error">*</span>@endif</label>
                        @if($section > 1)
                            <button type="button" class="btn danger remove-section-btn" data-section="{{ $section }}" style="padding:4px 10px;font-size:11px">Quitar</button>
                        @endif
                    </div>
                    <input name="section_{{ $section }}_title" value="{{ $titleValue }}" @required($section === 1) placeholder="{{ $sectionLabels[$section][0] }}">
                    <label style="margin-top:10px">{{ $sectionLabels[$section][1] }}</label>
                    <textarea name="section_{{ $section }}_content" style="min-height:130px" placeholder="{{ $sectionLabels[$section][1] }}">{{ $contentValue }}</textarea>
                </div>
            @endforeach
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

        if (clientSelect) {
            clientSelect.addEventListener('change', () => {
                const option = clientSelect.selectedOptions[0];
                if (!option || !option.value) return;

                recipientName.value = option.dataset.name || '';
                recipientTax.value = option.dataset.tax || '';
                recipientAddress.value = option.dataset.address || '';
            });
        }

        const addBtn = document.getElementById('add-section-btn');
        const sections = Array.from(document.querySelectorAll('.report-section'));

        function refreshAddButton() {
            const hidden = sections.some(s => s.style.display === 'none');
            addBtn.style.display = hidden ? '' : 'none';
        }

        addBtn?.addEventListener('click', () => {
            const next = sections.find(s => s.style.display === 'none');
            if (!next) return;
            next.style.display = '';
            next.querySelector('input')?.focus();
            refreshAddButton();
        });

        document.querySelectorAll('.remove-section-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrap = btn.closest('.report-section');
                wrap.querySelector('input').value = '';
                wrap.querySelector('textarea').value = '';
                wrap.style.display = 'none';
                refreshAddButton();
            });
        });

        refreshAddButton();

        const reportProfileSelect = document.getElementById('report-fiscal-profile-select');
        const reportLogoEmptyMessage = document.getElementById('report-profile-logos-empty');

        function refreshReportLogoVisibility() {
            const profileId = reportProfileSelect?.value || '';
            let visibleLogos = 0;

            document.querySelectorAll('[data-report-logo-option="logo"]').forEach(option => {
                const visible = (option.dataset.profileId || '') === String(profileId);
                option.style.display = visible ? '' : 'none';
                if (visible) visibleLogos++;
            });

            const checked = document.querySelector('input[name="logo_path"]:checked');
            const checkedOption = checked?.closest('[data-report-logo-option="logo"]');

            if (checkedOption && checkedOption.style.display === 'none') {
                checked.checked = false;
            }

            if (!document.querySelector('input[name="logo_path"]:checked')) {
                const firstVisibleLogo = Array.from(document.querySelectorAll('[data-report-logo-option="logo"] input[name="logo_path"]'))
                    .find(radio => radio.closest('[data-report-logo-option="logo"]')?.style.display !== 'none');
                if (firstVisibleLogo) {
                    firstVisibleLogo.checked = true;
                    firstVisibleLogo.dispatchEvent(new Event('change'));
                }
            }

            if (reportLogoEmptyMessage) {
                reportLogoEmptyMessage.style.display = visibleLogos === 0 ? '' : 'none';
            }
        }

        document.querySelectorAll('.report-logo-radio').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('[data-report-logo-option]').forEach(el => {
                    el.classList.remove('border-primary', 'bg-primary-soft-2');
                    el.classList.add('border-outline-variant');
                });
                if (radio.checked) {
                    radio.closest('label').classList.add('border-primary', 'bg-primary-soft-2');
                    radio.closest('label').classList.remove('border-outline-variant');
                }
            });
        });

        reportProfileSelect?.addEventListener('change', refreshReportLogoVisibility);
        refreshReportLogoVisibility();
    });
</script>
@endsection
