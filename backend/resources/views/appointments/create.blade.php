@extends('layouts.app')

@section('title', 'Nueva cita')
@section('subtitle', 'Agendar cita en el calendario compartido')

@section('content')
<form method="POST" action="{{ route('web.appointments.store') }}" class="form">
    @csrf
    <div class="invoice-grid">
        <section class="card form">
            <h3>Datos de la cita</h3>
            <div class="fields">
                <div class="field span-2">
                    <label>Título *</label>
                    <input name="title" type="text" value="{{ old('title') }}" required placeholder="Ej: Revisión equipo cliente X">
                </div>
                <div class="field">
                    <label>Inicio *</label>
                    <input name="start_at" type="datetime-local"
                           value="{{ old('start_at', $defaultDate ? $defaultDate.'T09:00' : '') }}" required>
                </div>
                <div class="field">
                    <label>Fin *</label>
                    <input name="end_at" type="datetime-local"
                           value="{{ old('end_at', $defaultDate ? $defaultDate.'T10:00' : '') }}" required>
                </div>
                <div class="field">
                    <label>Cliente</label>
                    <select name="client_id">
                        <option value="">Sin cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id') === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Estado inicial</label>
                    <select name="status">
                        @foreach(\App\Models\Appointment::STATUS_LABELS as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'pending') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field span-2" style="position:relative">
                    <label>Ubicación</label>
                    <div style="position:relative">
                        <input id="location-input" name="location" type="text" autocomplete="off"
                               value="{{ old('location') }}"
                               placeholder="Escribe una dirección para buscar…"
                               style="padding-right:38px">
                        <i data-lucide="map-pin" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#ef4444;pointer-events:none"></i>
                    </div>
                    {{-- Autocomplete dropdown --}}
                    <ul id="location-suggestions"
                        style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #c4c5d7;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:100;margin-top:4px;padding:4px 0;list-style:none;max-height:260px;overflow-y:auto"></ul>
                    {{-- Map preview --}}
                    <div id="location-map-wrap" style="display:none;margin-top:10px;border-radius:10px;overflow:hidden;border:1px solid #c4c5d7">
                        <iframe id="location-map"
                                width="100%" height="240"
                                style="border:0;display:block"
                                loading="lazy">
                        </iframe>
                    </div>
                    <input type="hidden" id="location-lat" name="location_lat">
                    <input type="hidden" id="location-lng" name="location_lng">
                </div>
                <div class="field span-2">
                    <label>Servicio a realizar</label>
                    <textarea name="service_description" placeholder="Describe el trabajo a realizar...">{{ old('service_description') }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Observaciones</label>
                    <textarea name="observations">{{ old('observations') }}</textarea>
                </div>
            </div>

            {{-- Contacts --}}
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 style="margin:0">Contactos</h3>
                    <button type="button" onclick="addContact()" class="btn" style="padding:6px 12px;font-size:12px">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Agregar
                    </button>
                </div>
                <div id="contacts-list">
                    @foreach(old('contacts', []) as $i => $contact)
                    <div class="contact-row fields" style="margin-bottom:10px">
                        <div class="field"><label>Nombre</label><input name="contacts[{{ $i }}][name]" value="{{ $contact['name'] ?? '' }}"></div>
                        <div class="field"><label>Teléfono</label><input name="contacts[{{ $i }}][phone]" value="{{ $contact['phone'] ?? '' }}"></div>
                        <div class="field"><label>Correo</label><input name="contacts[{{ $i }}][email]" type="email" value="{{ $contact['email'] ?? '' }}"></div>
                        <div class="field" style="display:flex;align-items:flex-end"><button type="button" class="btn danger" onclick="this.closest('.contact-row').remove()">Quitar</button></div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="card">
            <h3>Resumen</h3>
            <p class="muted text-[13px]">
                Solo el creador y el admin pueden editar esta cita después de crearla.
                Las notificaciones push se envían automáticamente a todos los usuarios ADMIN y VENDEDOR.
            </p>
            <div class="mt-4 space-y-2">
                <button class="btn primary w-full" type="submit">Agendar cita</button>
                <a class="btn w-full justify-center" href="{{ route('web.appointments.index') }}">Cancelar</a>
            </div>
        </aside>
    </div>
</form>

<script>
// ── Contacts ──────────────────────────────────────────────────────────────────
let contactIdx = {{ count(old('contacts', [])) }};
function addContact() {
    document.getElementById('contacts-list').insertAdjacentHTML('beforeend', `
    <div class="contact-row fields" style="margin-bottom:10px">
        <div class="field"><label>Nombre</label><input name="contacts[${contactIdx}][name]"></div>
        <div class="field"><label>Teléfono</label><input name="contacts[${contactIdx}][phone]"></div>
        <div class="field"><label>Correo</label><input name="contacts[${contactIdx}][email]" type="email"></div>
        <div class="field" style="display:flex;align-items:flex-end"><button type="button" class="btn danger" onclick="this.closest('.contact-row').remove()">Quitar</button></div>
    </div>`);
    contactIdx++;
    if (window.lucide) window.lucide.createIcons();
}

// ── Location autocomplete (Nominatim / OpenStreetMap — sin API key) ───────────
const locationInput   = document.getElementById('location-input');
const suggestions     = document.getElementById('location-suggestions');
const mapWrap         = document.getElementById('location-map-wrap');
const mapIframe       = document.getElementById('location-map');
const latInput        = document.getElementById('location-lat');
const lngInput        = document.getElementById('location-lng');

let debounceTimer;

locationInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const q = locationInput.value.trim();
    if (q.length < 3) { hideSuggestions(); return; }

    debounceTimer = setTimeout(() => searchAddress(q), 350);
});

locationInput.addEventListener('keydown', e => {
    const items = suggestions.querySelectorAll('li');
    const active = suggestions.querySelector('li.active');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = active ? active.nextElementSibling : items[0];
        if (next) { active?.classList.remove('active'); next.classList.add('active'); next.scrollIntoView({block:'nearest'}); }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = active?.previousElementSibling;
        if (prev) { active.classList.remove('active'); prev.classList.add('active'); prev.scrollIntoView({block:'nearest'}); }
    } else if (e.key === 'Enter') {
        if (active) { e.preventDefault(); active.click(); }
    } else if (e.key === 'Escape') {
        hideSuggestions();
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('#location-input') && !e.target.closest('#location-suggestions')) {
        hideSuggestions();
    }
});

async function searchAddress(q) {
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&q=${encodeURIComponent(q)}`,
            { headers: { 'Accept-Language': 'es' } }
        );
        const data = await res.json();
        renderSuggestions(data);
    } catch (_) {}
}

function renderSuggestions(results) {
    if (!results.length) { hideSuggestions(); return; }

    suggestions.innerHTML = results.map((r, i) => {
        const icon = placeIcon(r.type || r.class);
        return `<li data-lat="${r.lat}" data-lng="${r.lon}" data-label="${escHtml(r.display_name)}"
                    style="display:flex;align-items:flex-start;gap:10px;padding:9px 14px;cursor:pointer;font-size:13px;line-height:1.3;transition:background .1s"
                    onmouseenter="this.classList.add('active')"
                    onmouseleave="this.classList.remove('active')">
                  <span style="font-size:16px;margin-top:1px;flex-shrink:0">${icon}</span>
                  <span>${escHtml(r.display_name)}</span>
                </li>`;
    }).join('');

    suggestions.querySelectorAll('li').forEach(li => {
        li.addEventListener('click', () => selectSuggestion(li));
    });

    // Highlight active li
    suggestions.addEventListener('mouseover', () => {
        suggestions.querySelectorAll('li').forEach(l => l.classList.remove('active'));
    });

    applySuggestionStyles();
    suggestions.style.display = 'block';
}

function applySuggestionStyles() {
    // Inject hover style once
    if (!document.getElementById('sug-style')) {
        const s = document.createElement('style');
        s.id = 'sug-style';
        s.textContent = '#location-suggestions li.active { background:#eef2ff; }';
        document.head.appendChild(s);
    }
}

function selectSuggestion(li) {
    const label = li.dataset.label;
    const lat   = li.dataset.lat;
    const lng   = li.dataset.lng;

    locationInput.value = label;
    latInput.value = lat;
    lngInput.value = lng;
    hideSuggestions();
    showMapPreview(label, lat, lng);
}

function showMapPreview(label, lat, lng) {
    const encoded = encodeURIComponent(label);
    mapIframe.src = `https://maps.google.com/maps?q=${encoded}&output=embed&z=16`;
    mapWrap.style.display = 'block';
}

function hideSuggestions() {
    suggestions.style.display = 'none';
    suggestions.innerHTML = '';
}

function placeIcon(type) {
    const icons = { road:'🛣️', residential:'🏘️', house:'🏠', building:'🏢',
                    hospital:'🏥', restaurant:'🍽️', school:'🏫', hotel:'🏨',
                    supermarket:'🛒', pharmacy:'💊', place:'📍' };
    return icons[type] || '📍';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Show map if value already set (on validation error)
const existingVal = locationInput.value.trim();
if (existingVal) showMapPreview(existingVal, '', '');
</script>
@endsection
