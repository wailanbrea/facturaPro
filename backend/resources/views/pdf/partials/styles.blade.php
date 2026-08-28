<style>
    /* ------------------------------------------------------------------
       Hoja A4 apaisada.
       El tamano y la orientacion salen SOLO de aqui: el generador invoca a
       Chrome con --print-to-pdf y no pasa ningun flag de pagina.
       ------------------------------------------------------------------ */
    @page { size: A4 landscape; margin: 0; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: var(--ink);
        font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
        font-size: 7.4pt;
        line-height: 1.25;
    }

    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    :root {
        --navy: #0d2b5e;
        --navy-deep: #062a55;
        --accent: #f97316;
        --ok: #16a34a;
        --ok-soft: #dcfce7;
        --ink: #111827;
        --muted: #64748b;
        --line: #cbd5e1;
        --line-soft: #e2e8f0;
        --zebra: #f8fafc;
    }

    /* 209.6 y no 210: con el alto exacto, cualquier redondeo subpixel de
       Chrome desborda un pixel al folio siguiente y aparecen paginas en blanco. */
    .sheet {
        position: relative;
        width: 297mm;
        height: 209.6mm;
        padding: 6mm 7mm 5mm;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 2.6mm;
        page-break-after: always;
        break-after: page;
    }

    /* Sin esto, la ultima hoja genera un folio vacio al final del PDF. */
    .sheet:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .ico { flex: 0 0 auto; vertical-align: -1px; }

    /* ---------------------------- Cabecera ---------------------------- */
    .doc-head {
        display: grid;
        grid-template-columns: 62mm minmax(0, 1fr) 78mm;
        gap: 4mm;
        align-items: start;
    }

    .logo-box {
        height: 23mm;
        border: 0.35mm solid var(--line);
        border-radius: 1.2mm;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 1.6mm;
    }

    .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

    .logo-initial {
        width: 15mm;
        height: 15mm;
        border: 0.7mm solid var(--navy);
        border-radius: 1mm;
        color: var(--navy);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15pt;
        font-weight: 800;
    }

    /* Los sellos ocupan toda la altura del logo para que la banda superior no
       quede medio vacia; por eso min-height y align-content en vez de padding. */
    .trust-badges {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 3mm 2.4mm;
        min-height: 23mm;
        align-content: center;
    }

    .badge {
        display: flex;
        align-items: center;
        gap: 1.8mm;
        color: var(--navy);
    }

    .badge-text {
        font-size: 6.5pt;
        font-weight: 700;
        line-height: 1.25;
        text-transform: uppercase;
        letter-spacing: .01em;
        color: var(--muted);
    }

    .doc-title {
        text-align: right;
        font-size: 16.5pt;
        font-weight: 800;
        line-height: 1.05;
        color: var(--navy);
        text-transform: uppercase;
    }

    .doc-title.two-line { font-size: 12.6pt; }

    .doc-number-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 17mm;
        gap: 3mm;
        align-items: center;
        margin-top: 2.4mm;
    }

    .doc-number {
        background: var(--navy);
        color: #fff;
        border-radius: 1.4mm;
        padding: 1.8mm 3mm;
        text-align: center;
    }

    .doc-number-label {
        font-size: 5.6pt;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        opacity: .85;
    }

    .doc-number-value {
        font-size: 10.4pt;
        font-weight: 800;
        letter-spacing: .02em;
        margin-top: .6mm;
    }

    .qr-block { display: flex; align-items: center; gap: 2mm; }
    .qr-block img { width: 16mm; height: 16mm; display: block; }

    .qr-caption {
        font-size: 5.2pt;
        line-height: 1.25;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
    }

    .qr-caption span { display: block; font-weight: 400; text-transform: none; }

    .page-flag {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--navy);
        color: #fff;
        font-size: 5.6pt;
        font-weight: 700;
        letter-spacing: .08em;
        padding: 1.3mm 3.4mm;
        border-bottom-left-radius: 1.4mm;
    }

    /* ----------------------------- Tarjetas ---------------------------- */
    .cards-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 3mm;
        align-items: start;
    }

    .card-box {
        border: 0.3mm solid var(--line);
        border-radius: 1.4mm;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .card-head {
        background: var(--navy);
        color: #fff;
        display: flex;
        align-items: center;
        gap: 1.6mm;
        padding: 1.5mm 2.4mm;
        font-size: 6pt;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .card-body { padding: 2.2mm 2.6mm; flex: 1; }

    .card-body .name {
        font-weight: 700;
        font-size: 7.6pt;
        margin-bottom: 1.4mm;
    }

    .card-body .line { margin-bottom: .9mm; }
    .card-body .line:last-child { margin-bottom: 0; }
    .card-body .label { font-weight: 700; }

    /* Filas etiqueta/valor de las tarjetas de detalles y resumen. */
    .kv { display: flex; justify-content: space-between; gap: 2mm; padding: 1.15mm 0; }
    .kv + .kv { border-top: 0.25mm solid var(--line-soft); }
    .kv dt { color: var(--ink); font-weight: 600; }
    .kv dd { margin: 0; text-align: right; }
    .kv dd.strong { font-weight: 700; }
    .kv dd.paid { color: var(--ok); font-weight: 800; }

    /* Cajas destacadas del resumen. */
    .totals-highlight { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; margin-top: 1.6mm; }
    .totals-highlight.single { grid-template-columns: 1fr; }

    .hl {
        padding: 2mm 2.4mm;
        color: #fff;
        background: var(--navy-deep);
    }

    .hl.green { background: var(--ok); }
    .hl-label { font-size: 5.4pt; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; opacity: .9; }
    .hl-value { font-size: 11.5pt; font-weight: 800; margin-top: .5mm; }
    .hl.green .hl-value { font-size: 9.6pt; }

    /* --------------------------- Zona de trabajo ----------------------- */
    /* Sin `flex: 1`: la zona de trabajo ocupa solo lo que necesita y los
       recuadros inferiores suben detras de ella. Con `flex: 1` se estiraba
       hasta el pie y en un documento de pocas lineas dejaba medio folio en
       blanco en el centro. Sigue pudiendo encoger (`0 1 auto` + min-height)
       para que una tabla llena no desborde la hoja. */
    .work-row { display: grid; gap: 3mm; flex: 0 1 auto; min-height: 0; align-items: start; }
    .work-row.with-aside { grid-template-columns: minmax(0, 1fr) 62mm; }
    .work-row.with-two-asides { grid-template-columns: minmax(0, 1fr) 52mm 58mm; }
    .work-row.full { grid-template-columns: minmax(0, 1fr); }

    .items {
        width: 100%;
        border-collapse: collapse;
        border: 0.3mm solid var(--line);
        border-radius: 1.4mm;
        overflow: hidden;
        table-layout: fixed;
    }

    .items thead th {
        background: var(--navy);
        color: #fff;
        font-size: 5.9pt;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        padding: 1.7mm 2mm;
        text-align: left;
    }

    .items td {
        padding: 1.5mm 2mm;
        border-top: 0.25mm solid var(--line-soft);
        vertical-align: top;
    }

    .items tbody tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .items tbody tr:nth-child(even) td { background: var(--zebra); }

    .items .c-idx { width: 8mm; text-align: center; color: var(--muted); }
    .items .c-qty { width: 16mm; text-align: center; }
    .items .c-price { width: 26mm; text-align: right; }
    .items .c-amount { width: 26mm; text-align: right; font-weight: 700; }

    .items .desc {
        overflow-wrap: anywhere;
        display: block;
        overflow: hidden;
    }

    .items-caption {
        display: flex;
        align-items: center;
        gap: 1.6mm;
        background: var(--navy);
        color: #fff;
        padding: 1.5mm 2.4mm;
        font-size: 6pt;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        border-radius: 1.4mm 1.4mm 0 0;
    }

    /* --------------------------- Cajas de texto ------------------------ */
    .note-box {
        border: 0.3mm solid var(--line);
        border-radius: 1.4mm;
        padding: 2.2mm 2.6mm;
        display: flex;
        gap: 2.2mm;
        align-items: flex-start;
    }

    .note-box .note-ico { color: var(--navy); padding-top: .3mm; }
    .note-title { font-size: 6.1pt; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--navy); margin-bottom: 1mm; }
    .note-text { color: var(--ink); line-height: 1.3; white-space: pre-line; overflow: hidden; }
    .note-text.muted { color: var(--muted); }

    .bottom-row .note-box {
        padding: 1.4mm 2mm;
    }

    .bottom-row .note-title {
        margin-bottom: .5mm;
    }

    .bottom-row .note-text {
        line-height: 1.1;
    }

    .bottom-sheet .bottom-row {
        margin-top: auto;
    }

    .aside-box { border: 0.3mm solid var(--line); border-radius: 1.4mm; padding: 1.8mm 2.2mm; }
    .aside-box + .aside-box { margin-top: 2mm; }
    .aside-box .note-title { font-size: 5.8pt; margin-bottom: 0.8mm; }
    .aside-box .note-text { font-size: 6.2pt; line-height: 1.22; }

    .check-list { margin: 1.2mm 0 0; padding: 0; list-style: none; }
    .check-list li { display: flex; align-items: flex-start; gap: 1.4mm; padding: .6mm 0; }
    .check-list .ico { color: var(--ok); margin-top: .3mm; }

    .bottom-row { display: grid; gap: 3mm; }
    .sheet > .bottom-row { margin-top: auto; }
    .bottom-row.cols-3 { grid-template-columns: repeat(3, 1fr); }
    .bottom-row.cols-4 { grid-template-columns: 1.35fr 1fr 1fr 1.1fr; }
    .quotation-bottom-row { grid-template-columns: repeat(2, 1fr); }
    .quotation-bottom-row .quotation-acceptance { grid-column: 1 / -1; }
    .quotation-acceptance .note-text p { margin: 0; }
    .quotation-bottom-row .note-text { color: var(--muted); }


    .iban { font-weight: 700; letter-spacing: .01em; }


    /* --------------------------- Pagina legal -------------------------- */
    .legal-band {
        display: inline-flex;
        align-items: center;
        background: var(--navy);
        color: #fff;
        font-size: 6pt;
        font-weight: 700;
        letter-spacing: .08em;
        padding: 1.4mm 3.4mm;
        border-radius: 1.2mm;
        align-self: flex-start;
    }

    /* Igual que la zona de trabajo de la primera hoja: el contenido se apoya
       arriba y el blanco sobrante queda al final. Los bloques van mas grandes
       que en el diseno original para llenar mejor el folio. */
    .legal-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 7mm 6mm;
        margin-top: 5mm;
        flex: 0 1 auto;
        min-height: 0;
        align-content: start;
    }

    .legal-item { display: grid; grid-template-columns: 13mm minmax(0, 1fr); gap: 2.8mm; align-items: start; }

    .legal-ico {
        width: 13mm;
        height: 13mm;
        border-radius: 50%;
        border: 0.4mm solid currentColor;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .legal-ico.navy { color: var(--navy); }
    .legal-ico.accent { color: var(--accent); }

    .legal-title { font-size: 8pt; font-weight: 800; text-transform: uppercase; color: var(--navy); margin-bottom: 1.3mm; }
    .legal-text { font-size: 7.8pt; color: var(--ink); line-height: 1.4; }

    .eco-note {
        margin-top: auto;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 2.4mm;
        background: var(--ok-soft);
        border-radius: 1.6mm;
        padding: 2.2mm 3.4mm;
        max-width: 92mm;
    }

    .eco-note .ico { color: var(--ok); }
    .eco-text { color: #14532d; line-height: 1.3; }

    /* ----------------------------- Marca de agua ----------------------- */
    .watermark {
        position: absolute;
        left: 96mm;
        top: 88mm;
        z-index: 5;
        transform: rotate(-24deg);
        color: rgba(198, 0, 0, .42);
        border: 0.9mm solid rgba(198, 0, 0, .38);
        border-radius: 2mm;
        padding: 2.6mm 10mm;
        font-size: 26pt;
        font-weight: 800;
        letter-spacing: .06em;
        pointer-events: none;
    }

    .verify-code { font-family: "Courier New", Courier, monospace; font-weight: 700; letter-spacing: .04em; }
    .right { text-align: right; }
    .muted { color: var(--muted); }
    .nowrap { white-space: nowrap; }
</style>
