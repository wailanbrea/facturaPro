@php
    /** @var \App\Models\Appointment|null $appointment */
    $appointment = $appointment ?? null;
    $locValue = old('location', $appointment->location ?? '');
    $latValue = old('location_lat', $appointment->location_lat ?? '');
    $lngValue = old('location_lng', $appointment->location_lng ?? '');
@endphp
<div class="field span-2" style="position:relative">
    <label>Ubicación</label>
    <div style="position:relative">
        <input id="location-input" name="location" type="text" autocomplete="off"
               value="{{ $locValue }}"
               placeholder="Escribe una dirección o pega un enlace/dirección de Google Maps…"
               style="padding-right:38px">
        <i data-lucide="map-pin" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#ef4444;pointer-events:none"></i>
    </div>
    <p class="muted" style="font-size:12px;margin:6px 0 0">
        Puedes pegar la dirección que te comparte el cliente desde Google Maps (o sus coordenadas).
        Si el pin no cae exactamente donde debe, arrástralo en el mapa para fijar el punto correcto.
    </p>
    {{-- Autocomplete dropdown --}}
    <ul id="location-suggestions"
        style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #c4c5d7;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:1100;margin-top:4px;padding:4px 0;list-style:none;max-height:260px;overflow-y:auto"></ul>
    {{-- Interactive map with draggable pin --}}
    <div id="location-map-wrap" style="{{ ($locValue || ($latValue !== '' && $latValue !== null)) ? '' : 'display:none;' }}margin-top:10px;border-radius:10px;overflow:hidden;border:1px solid #c4c5d7">
        <div id="location-map" style="height:280px;width:100%"></div>
        <div id="pin-status" class="muted" style="font-size:12px;padding:8px 12px;background:#f8f9ff;border-top:1px solid #e2e1ed">
            Arrastra el pin rojo para ajustar la ubicación exacta.
        </div>
    </div>
    <input type="hidden" id="location-lat" name="location_lat" value="{{ $latValue }}">
    <input type="hidden" id="location-lng" name="location_lng" value="{{ $lngValue }}">
</div>

@once
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush
@endonce

@push('location-picker-script')
<script>
(function () {
    const locationInput = document.getElementById('location-input');
    const suggestions   = document.getElementById('location-suggestions');
    const mapWrap       = document.getElementById('location-map-wrap');
    const latInput      = document.getElementById('location-lat');
    const lngInput      = document.getElementById('location-lng');
    const pinStatus     = document.getElementById('pin-status');

    let map = null;
    let marker = null;
    let debounceTimer;

    function ensureMap(lat, lng) {
        mapWrap.style.display = 'block';

        if (!map) {
            map = L.map('location-map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                setCoords(pos.lat, pos.lng);
                pinStatus.textContent = 'Pin fijado en ' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + ' — se guardará esta posición exacta.';
            });
            map.on('click', (e) => {
                marker.setLatLng(e.latlng);
                setCoords(e.latlng.lat, e.latlng.lng);
                pinStatus.textContent = 'Pin fijado en ' + e.latlng.lat.toFixed(6) + ', ' + e.latlng.lng.toFixed(6) + ' — se guardará esta posición exacta.';
            });
            setTimeout(() => map.invalidateSize(), 150);
        } else {
            map.setView([lat, lng], Math.max(map.getZoom(), 15));
            marker.setLatLng([lat, lng]);
        }
    }

    function setCoords(lat, lng) {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
    }

    function placeMarker(lat, lng) {
        lat = parseFloat(lat); lng = parseFloat(lng);
        if (isNaN(lat) || isNaN(lng)) return;
        setCoords(lat, lng);
        ensureMap(lat, lng);
    }

    // ── Extraer coordenadas de un enlace o texto de Google Maps ──────────────
    function parseGoogleMaps(text) {
        const patterns = [
            /@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/,            // .../@41.38,2.17,15z
            /[?&]q=(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/,        // ...?q=41.38,2.17
            /!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/,          // ...!3d41.38!4d2.17
            /^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/, // "41.38, 2.17" pegado directo
        ];
        for (const re of patterns) {
            const m = text.match(re);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
        }
        return null;
    }

    async function reverseGeocode(lat, lng) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, { headers: { 'Accept-Language': 'es' } });
            const data = await res.json();
            if (data && data.display_name) locationInput.value = data.display_name;
        } catch (_) {}
    }

    locationInput.addEventListener('paste', (e) => {
        const text = (e.clipboardData || window.clipboardData).getData('text');
        const coords = parseGoogleMaps(text);
        if (coords) {
            e.preventDefault();
            locationInput.value = text;
            placeMarker(coords.lat, coords.lng);
            reverseGeocode(coords.lat, coords.lng);
            hideSuggestions();
        }
    });

    locationInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = locationInput.value.trim();

        const coords = parseGoogleMaps(q);
        if (coords) {
            placeMarker(coords.lat, coords.lng);
            hideSuggestions();
            return;
        }

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
        if (!e.target.closest('#location-input') && !e.target.closest('#location-suggestions')) hideSuggestions();
    });

    async function searchAddress(q) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&q=${encodeURIComponent(q)}`, { headers: { 'Accept-Language': 'es' } });
            const data = await res.json();
            renderSuggestions(data);
        } catch (_) {}
    }

    function renderSuggestions(results) {
        if (!results.length) { hideSuggestions(); return; }
        suggestions.innerHTML = results.map(r =>
            `<li data-lat="${r.lat}" data-lng="${r.lon}" data-label="${escHtml(r.display_name)}"
                 style="display:flex;align-items:flex-start;gap:10px;padding:9px 14px;cursor:pointer;font-size:13px;line-height:1.3;transition:background .1s"
                 onmouseenter="this.classList.add('active')" onmouseleave="this.classList.remove('active')">
               <span style="font-size:16px;margin-top:1px;flex-shrink:0">📍</span>
               <span>${escHtml(r.display_name)}</span>
             </li>`).join('');
        suggestions.querySelectorAll('li').forEach(li => li.addEventListener('click', () => selectSuggestion(li)));
        if (!document.getElementById('sug-style')) {
            const s = document.createElement('style');
            s.id = 'sug-style';
            s.textContent = '#location-suggestions li.active{background:#eef2ff}';
            document.head.appendChild(s);
        }
        suggestions.style.display = 'block';
    }

    function selectSuggestion(li) {
        locationInput.value = li.dataset.label;
        hideSuggestions();
        placeMarker(li.dataset.lat, li.dataset.lng);
    }

    function hideSuggestions() { suggestions.style.display = 'none'; suggestions.innerHTML = ''; }
    function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── Estado inicial ────────────────────────────────────────────────────────
    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);
    const initialText = locationInput.value.trim();

    if (!isNaN(initialLat) && !isNaN(initialLng)) {
        ensureMap(initialLat, initialLng);
    } else if (initialText) {
        // Geocodifica la dirección guardada para posicionar el pin.
        searchInitial(initialText);
    }

    async function searchInitial(q) {
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`, { headers: { 'Accept-Language': 'es' } });
            const data = await res.json();
            if (data.length) ensureMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
        } catch (_) {}
    }
})();
</script>
@endpush
