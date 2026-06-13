# FASE 8 - Plantilla HTML/CSS de factura

Fecha: 2026-05-21

## Objetivo

Crear la plantilla Blade unica que servira como vista previa web y como origen de render para PDF en FASE 9.

## Alcance implementado

- Vista `resources/views/pdf/invoice.blade.php`.
- Ruta web protegida `GET /invoices/{invoice}/preview`.
- Accion "Vista previa" desde el detalle de factura.
- Render de dos copias:
  - `ORIGINAL: CLIENTE`
  - `COPIA: VENDEDOR`
- CSS A4 aislado dentro de la plantilla.
- Soporte de logo desde `fiscal_profiles.logo_path`; si no existe, se muestra inicial del emisor.
- Soporte de moneda dinamica usando `CurrencyFormatterService`.
- Soporte de filas variables con `page-break-inside: avoid`.
- Soporte de textos largos en descripcion, garantia, observaciones y texto legal mediante wrapping seguro.
- Snapshot de texto legal en facturas creadas desde web.

## Decision tecnica

La plantilla no calcula importes. Solo muestra datos ya calculados y persistidos por backend:

- `unit_cost`
- `tax_amount`
- `unit_price`
- `line_total`
- `subtotal`
- `tax_total`
- `total`
- `amount_received`
- `balance_due`

Esto mantiene la decision base: el backend es la fuente de verdad para totales y estados.

## Estructura visual

La plantilla replica las secciones oficiales identificadas en la FASE 0:

- Encabezado de emisor/logo.
- Titulo `FACTURA`.
- Numero o estado `BORRADOR`.
- Fecha.
- Datos del cliente.
- Vencimiento y termino de pago.
- Tabla de lineas:
  - Descripcion.
  - Cantidad.
  - Costo unitario.
  - IVA.
  - Precio unitario.
  - Importe.
- Totales.
- Garantia / observaciones.
- Cuentas bancarias.
- Recibido por.
- Preparado por.
- Conformidad del cliente.
- Texto legal.

## Archivos modificados

- `backend/routes/web.php`
- `backend/app/Http/Controllers/Web/InvoiceController.php`
- `backend/resources/views/invoices/show.blade.php`
- `backend/resources/views/pdf/invoice.blade.php`
- `backend/tests/Feature/Web/AdminPanelTest.php`

## Validacion ejecutada

- `php -l app/Http/Controllers/Web/InvoiceController.php`.
- `php artisan route:list --path=invoices`.
- `php artisan test --filter=invoice_preview_renders_official_template`.
- `php artisan test`.
- Render visual en Chrome headless contra `http://127.0.0.1:8001/invoices/2/preview`.
- Captura de pantalla completa temporal:
  - `C:\Users\waila\AppData\Local\Temp\facturapro-invoice-preview-compact.png`
- PDF temporal generado desde Chrome para validar print CSS:
  - `C:\Users\waila\AppData\Local\Temp\facturapro-invoice-preview.pdf`
- Validacion con `pypdf`:
  - 2 paginas.
  - MediaBox A4: `595.92 x 842.88`.
  - Textos clave presentes: `FACTURA`, `ORIGINAL: CLIENTE`, `COPIA: VENDEDOR`.

Resultado de pruebas automatizadas:

```text
Tests: 40 passed (150 assertions)
```

## Riesgos pendientes

- FASE 8 valida que la plantilla imprime correctamente desde navegador, pero no integra almacenamiento ni descarga PDF. Eso pertenece a FASE 9.
- El logo depende de que `logo_path` apunte a un archivo publico valido en `storage`.
- Facturas con muchas lineas pueden generar paginas adicionales. La plantilla evita cortes internos de filas, pero FASE 9 debe validar el paginado final con Browsershot/Puppeteer.
