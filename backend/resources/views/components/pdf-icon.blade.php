@props([
    'name',
    'size' => 10,
    'stroke' => null,
])
@php
    // Trazo mas fino en tamanos pequenos: a 10px o menos un stroke de 2
    // empasta el dibujo al rasterizar el PDF.
    $strokeWidth = $stroke ?? ($size <= 10 ? 1.6 : 1.9);

    // Iconos de trazo sobre rejilla 24x24. Se usan en lugar de glifos Unicode
    // porque los emoji dependen de la fuente del sistema y Chrome headless los
    // dibuja distinto en Windows y en Linux.
    $paths = [
        'shield-check' => '<path d="M12 2 4 5.5v6c0 4.9 3.4 9 8 10.5 4.6-1.5 8-5.6 8-10.5v-6z"/><path d="m8.7 11.8 2.3 2.3 4.3-4.3"/>',
        'award' => '<circle cx="12" cy="9" r="5.2"/><path d="m8.4 13.2-1.3 7.3 4.9-2.6 4.9 2.6-1.3-7.3"/>',
        'package' => '<path d="M20 8.4v7.2a1.6 1.6 0 0 1-.9 1.4l-6.3 3.3a1.6 1.6 0 0 1-1.6 0l-6.3-3.3a1.6 1.6 0 0 1-.9-1.4V8.4"/><path d="m4 7.6 8-4.2 8 4.2-8 4.2z"/><path d="M12 11.8V20"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6.8V12l3.4 2"/>',
        'building' => '<path d="M4 21V5.2a1.2 1.2 0 0 1 1.2-1.2h9.6A1.2 1.2 0 0 1 16 5.2V21"/><path d="M16 10h3.2A1.2 1.2 0 0 1 20.4 11v10"/><path d="M7.4 8h1.8M7.4 12h1.8M7.4 16h1.8M12 8h1.4M12 12h1.4M12 16h1.4"/><path d="M3 21h18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.6 20.4a7.6 7.6 0 0 1 14.8 0"/>',
        'clipboard-list' => '<path d="M9 4.4H7.4A1.4 1.4 0 0 0 6 5.8v13.8A1.4 1.4 0 0 0 7.4 21h9.2a1.4 1.4 0 0 0 1.4-1.4V5.8a1.4 1.4 0 0 0-1.4-1.4H15"/><rect x="9" y="2.6" width="6" height="3.6" rx="1"/><path d="M9.4 11h5.2M9.4 14.6h5.2M9.4 18h3"/>',
        'calculator' => '<rect x="4.5" y="2.6" width="15" height="18.8" rx="1.8"/><path d="M8 6.6h8v3.2H8z"/><path d="M8.4 13.6h.01M12 13.6h.01M15.6 13.6h.01M8.4 17.4h.01M12 17.4h.01M15.6 17.4h.01"/>',
        'calendar' => '<rect x="3.4" y="5" width="17.2" height="16" rx="1.8"/><path d="M3.4 10h17.2M8.2 3v4M15.8 3v4"/>',
        'calendar-check' => '<rect x="3.4" y="5" width="17.2" height="16" rx="1.8"/><path d="M3.4 10h17.2M8.2 3v4M15.8 3v4"/><path d="m9.2 14.8 2 2 3.6-3.6"/>',
        'euro' => '<path d="M17 6.6a6.6 6.6 0 0 0-9.6 3.2M17 17.4a6.6 6.6 0 0 1-9.6-3.2"/><path d="M4.6 10.4h8M4.6 13.6h8"/>',
        'wrench' => '<path d="M20 5.4a5.4 5.4 0 0 1-7 7L6.2 19.2a2 2 0 0 1-2.8-2.8L10.2 9.6a5.4 5.4 0 0 1 7-7l-3 3 2.2 2.2z"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.6"/><path d="m20 20-4.6-4.6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.2 12.2 2.6 2.6 5-5"/>',
        'check' => '<path d="m4.6 12.4 4.8 4.8L19.4 7.2"/>',
        'landmark' => '<path d="M3.6 9.6 12 4l8.4 5.6"/><path d="M6 10.6v7.6M10 10.6v7.6M14 10.6v7.6M18 10.6v7.6"/><path d="M3.4 20.4h17.2"/>',
        'pen-line' => '<path d="M14.6 4.6a1.9 1.9 0 0 1 2.7 2.7L8.4 16.2l-3.6 1 1-3.6z"/><path d="M4 21h16"/>',
        'lock' => '<rect x="4.6" y="10.4" width="14.8" height="10.2" rx="1.8"/><path d="M8.2 10.4V7.6a3.8 3.8 0 0 1 7.6 0v2.8"/>',
        'scale' => '<path d="M12 3.4v17.2M6.6 20.6h10.8"/><path d="m6.4 6.6-3 7.2h6zM17.6 6.6l-3 7.2h6z"/><path d="M4.6 6.6h14.8"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9.2 9.2 5.6 5.6M14.8 9.2l-5.6 5.6"/>',
        'alert-triangle' => '<path d="M10.7 4.1 2.9 17.4a1.5 1.5 0 0 0 1.3 2.3h15.6a1.5 1.5 0 0 0 1.3-2.3L13.3 4.1a1.5 1.5 0 0 0-2.6 0z"/><path d="M12 9.4v4M12 16.6h.01"/>',
        'file-text' => '<path d="M14 3.2H7.4A1.8 1.8 0 0 0 5.6 5v14a1.8 1.8 0 0 0 1.8 1.8h9.2A1.8 1.8 0 0 0 18.4 19V7.6z"/><path d="M14 3.2v4.4h4.4"/><path d="M8.8 12.6h6.4M8.8 16.2h6.4"/>',
        'settings' => '<circle cx="12" cy="12" r="3.2"/><path d="M19.2 14.6a1.5 1.5 0 0 0 .3 1.7l.1.1a1.8 1.8 0 1 1-2.6 2.6l-.1-.1a1.5 1.5 0 0 0-2.6 1.1v.2a1.8 1.8 0 1 1-3.6 0V20a1.5 1.5 0 0 0-2.6-1l-.1.1a1.8 1.8 0 1 1-2.6-2.6l.1-.1a1.5 1.5 0 0 0-1-2.6H4a1.8 1.8 0 1 1 0-3.6h.2a1.5 1.5 0 0 0 1-2.6l-.1-.1a1.8 1.8 0 1 1 2.6-2.6l.1.1a1.5 1.5 0 0 0 2.6-1V4a1.8 1.8 0 1 1 3.6 0v.2a1.5 1.5 0 0 0 2.6 1l.1-.1a1.8 1.8 0 1 1 2.6 2.6l-.1.1a1.5 1.5 0 0 0 1 2.6h.2a1.8 1.8 0 1 1 0 3.6H20a1.5 1.5 0 0 0-1.4 1z"/>',
        'leaf' => '<path d="M20 4s.8 7.6-3.6 12-11.2 3.6-11.2 3.6-.8-7.6 3.6-12S20 4 20 4z"/><path d="M4.6 20.4c3-3 6.4-6.4 10.4-10.4"/>',
        'map-pin' => '<path d="M19 10.4c0 5.4-7 11.2-7 11.2s-7-5.8-7-11.2a7 7 0 1 1 14 0z"/><circle cx="12" cy="10.2" r="2.6"/>',
        'hard-hat' => '<path d="M4 16.4a8 8 0 0 1 16 0"/><path d="M9.6 8.6V5.2a1.2 1.2 0 0 1 1.2-1.2h2.4a1.2 1.2 0 0 1 1.2 1.2v3.4"/><rect x="2.6" y="16.4" width="18.8" height="3.6" rx="1.4"/>',
        'qr' => '<rect x="3.4" y="3.4" width="7" height="7" rx="1"/><rect x="13.6" y="3.4" width="7" height="7" rx="1"/><rect x="3.4" y="13.6" width="7" height="7" rx="1"/><path d="M13.6 13.6h3v3h-3zM20.6 13.6v3M17.6 20.6h3M13.6 20.6h.01"/>',
    ];
@endphp
<svg class="ico" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round"
     stroke-linejoin="round">{!! $paths[$name] ?? '' !!}</svg>
