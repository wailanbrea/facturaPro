# FASE 9 - Generacion PDF

Fecha: 2026-05-21

## Objetivo

Generar PDF desde backend usando la plantilla HTML/CSS A4 de FASE 8, guardar el archivo en storage publico, persistir la ruta en la factura y exponer descarga protegida.

## Implementacion

Se implemento `InvoicePdfService` usando Chrome/Chromium headless como alternativa compatible con Windows/XAMPP.

No se agrego Browsershot/Puppeteer en esta fase porque el entorno ya cuenta con Chrome local y Laravel puede renderizar la vista Blade directamente a HTML temporal. Esto reduce dependencias y evita acoplar la generacion PDF a Node para el primer despliegue Windows.

## Flujo tecnico

1. `InvoicePdfService` valida que la factura tenga numero.
2. Renderiza `resources/views/pdf/invoice.blade.php` a un HTML temporal en `storage/app/private/pdf-temp`.
3. Ejecuta Chrome headless con `--print-to-pdf`.
4. Guarda el PDF en `storage/app/public/invoices`.
5. Devuelve la ruta relativa.
6. Controlador API o web actualiza `invoices.pdf_path`.
7. Se registra `activity_logs.action = invoice.pdf_generated`.
8. La descarga se realiza desde `Storage::disk('public')->download(...)`.

## Descubrimiento de Chrome

Orden de resolucion:

1. Variable de entorno `CHROME_PATH`.
2. `C:\Program Files\Google\Chrome\Application\chrome.exe`.
3. `C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`.
4. `C:\Program Files\Microsoft\Edge\Application\msedge.exe`.
5. Rutas Linux comunes de Chrome/Chromium.

Si no se encuentra ejecutable, el servicio falla con mensaje explicito para configurar `CHROME_PATH`.

## Endpoints

API Sanctum:

- `POST /api/invoices/{invoice}/generate-pdf`
- `GET /api/invoices/{invoice}/download-pdf`

Web autenticado:

- `POST /invoices/{invoice}/generate-pdf`
- `GET /invoices/{invoice}/download-pdf`

## Reglas

- El PDF final solo se genera para facturas emitidas con numero.
- Facturas en borrador mantienen vista previa HTML, pero no PDF final.
- Android no genera PDF; debe solicitar generacion/descarga al backend.
- El nombre de archivo se deriva de `invoice_number` sanitizado.

## Archivos modificados

- `backend/app/Services/InvoicePdfService.php`
- `backend/app/Http/Controllers/Api/InvoiceController.php`
- `backend/app/Http/Controllers/Web/InvoiceController.php`
- `backend/routes/web.php`
- `backend/resources/views/invoices/show.blade.php`
- `backend/tests/Feature/Api/InvoiceApiTest.php`
- `backend/tests/Feature/Web/AdminPanelTest.php`

## Validacion ejecutada

- `php -l` en servicio y controladores modificados.
- `php artisan route:list --path=invoices`.
- `php artisan test --filter=generate_and_download_pdf_endpoint`.
- `php artisan test --filter=authenticated_user_can_create_issue_and_pay_invoice`.
- `php artisan test`.
- Verificacion de archivo generado:
  - Ruta: `storage/app/public/invoices/FAC-000001.pdf`.
  - Tamano observado: `105672` bytes.
  - `pypdf` confirmo:
    - 2 paginas.
    - MediaBox aproximado A4: `594.96 x 841.92`.
    - Textos clave presentes: `FACTURA`, `ORIGINAL: CLIENTE`, `COPIA: VENDEDOR`.

Resultado de pruebas automatizadas:

```text
Tests: 40 passed (164 assertions)
```

## Riesgos pendientes

- En produccion se debe configurar `CHROME_PATH` si Chrome no esta instalado en una ruta comun.
- FASE 10 debe aplicar permisos granulares para generar y descargar PDF.
- FASE 15 debe validar permisos de carpetas y ejecucion de Chrome en el VPS Windows.
