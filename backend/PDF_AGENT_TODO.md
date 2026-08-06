# PDF Agent TODO — FacturaPro

## FASE 0: Auditoría del Entorno
- [x] Ejecutar comandos de auditoría (`php -v`, `composer --version`, `node -v`, `npm -v`, `where chrome`, `where msedge`).
- [x] Inspeccionar `composer.json`, `package.json`, `app/Models/`, `app/Services/`.
- [x] Crear `docs/pdf-template/ENVIRONMENT_AUDIT.md`.
- [x] Crear `docs/pdf-template/DEPENDENCY_PLAN.md` con propuesta de dependencias y solicitud de aprobación.
- [x] Instalación de herramientas de testing visual (`@playwright/test`, `puppeteer`, `pixelmatch`, `pngjs`, Chromium).

## FASE 1: Archivos de Control
- [x] Crear `PDF_AGENT_TODO.md`.
- [ ] Crear `docs/pdf-template/PDF_TEMPLATE_SPEC.md`.
- [x] Crear `docs/pdf-template/VARIABLE_MAP.md`.
- [ ] Crear `docs/pdf-template/VISUAL_ANCHORS.md`.
- [ ] Crear `docs/pdf-template/DECISIONS.md`.
- [ ] Crear `docs/pdf-template/ERRORS.md`.

## FASE 2: Referencias Visuales
- [ ] Copiar o ubicar `invoice-target.png` y `quotation-target.png` en `docs/pdf-template/references/`.
- [ ] Análisis de regiones y distribución A4 Landscape.

## FASE 3: Mapa de Datos
- [x] Mapear atributos reales de `Invoice`, `InvoiceItem`, `FiscalProfile`, `BankAccount`, `Currency`, `Warranty`.

## FASE 4 & 5: Plantillas Blade
- [ ] Construir `resources/views/pdf/invoice.blade.php`.
- [ ] Construir `resources/views/pdf/quotation.blade.php`.

## FASE 6 & 7: Partials y CSS de Impresión
- [ ] Crear partials reutilizables (`logo-header`, `issuer-card`, `client-card`, `bank-transfer`, `eco-notice`, `legal-grid`).
- [ ] Escribir CSS de impresión dedicado (A4 Landscape, `297mm x 210mm`).

## FASE 8 & 9: Fixtures y Rutas de Preview Local
- [ ] Crear `tests/pdf/fixtures/invoice.php` y `quotation.php`.
- [ ] Configurar rutas de vista previa local (`/pdf-preview/invoice` y `/pdf-preview/quotation`).

## FASE 10 - 13: Render, Comparación y Bucle Iterativo
- [ ] Generar PDF real con `InvoicePdfService`.
- [ ] Convertir PDF a PNG.
- [ ] Comparación visual y corrección iterativa (Máximo 8 iteraciones).

## FASE 14 - 17: QA, Pruebas y Entrega
- [ ] Paginación dinámica y validación sin páginas vacías.
- [ ] Pruebas funcionales y de no-regresión.
- [ ] Entrega final con `FINAL_REPORT.md`.
