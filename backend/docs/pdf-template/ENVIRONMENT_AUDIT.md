# Auditoría del Entorno de Generación PDF — FacturaPro

Fecha: 2026-08-05
Directorio: `C:\xampp\php\www\FacturaPro\backend`

## 1. Versiones y Binarios del Sistema
- **PHP**: `PHP 8.2.12 (cli) ZTS` (`C:\xampp\php\php.exe`)
- **Laravel Framework**: `^12.0`
- **Composer**: `2.8.11`
- **Node.js**: `v24.4.0`
- **npm**: `11.4.2`
- **Playwright**: `Version 1.62.1`
- **Navegadores instalados en el sistema**:
  - Microsoft Edge: `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe` (Detectado y Funcional)
  - Google Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe` (Detectado y Funcional)

## 2. Motor PDF Actual
- **Mecanismo de Renderizado**: `App\Services\InvoicePdfService.php` utiliza la extensión `Symfony\Component\Process\Process` para ejecutar Headless Chrome/Edge directamente mediante CLI (`--headless=new --print-to-pdf`).
- **Ventaja**: No requiere dependencias externas adicionales en producción para generar el PDF.
- **Plantillas Existentes**:
  - `resources/views/pdf/invoice.blade.php` (Existente)
  - `resources/views/pdf/report.blade.php` (Existente)
  - `resources/views/pdf/quotation.blade.php` (Pendiente de creación)

## 3. Dependencias PHP y Node Registradas
### Composer (`composer.json`)
- `chillerlan/php-qrcode`: `^5.0` (Generación nativa de QR)
- `laravel/framework`: `^12.0`
- `laravel/sanctum`: `^4.0`

### npm (`package.json`)
- `tailwindcss`: `^4.0.0`
- `vite`: `^6.0.11`
- `@tailwindcss/vite`: `^4.0.0`

## 4. Campos y Modelos Disponibles
- **`Invoice`**: `invoice_number`, `document_type` (`invoice` / `quotation`), `invoice_date`, `due_date`, `client_name`, `client_tax_id`, `client_address`, `client_city`, `seller_name`, `seller_tax_id`, `seller_address`, `seller_city`, `subtotal`, `tax_total`, `total`, `balance_due`, `status`, `legal_text`, `warranty_text`, `observations`, `pdf_path`, `customer_signature_path`.
- **`InvoiceItem`**: `description`, `quantity`, `unit_price`, `subtotal`, `tax_amount`, `total`, `sort_order`.
- **`FiscalProfile`**: Datos fiscales del emisor, NIF/CIF, dirección, logo.
- **`BankAccount`**: IBAN, SWIFT, titular, banco para transferencia bancaria.
- **`Currency`**: Código, símbolo, separador de miles y decimales.

## 5. Herramientas Opcionales para Comparación Visual
- **Browsershot / Puppeteer**: Para renderizado y captura Playwright en pruebas.
- **MuPDF (`mutool`) / ImageMagick (`magick`)**: No detectados en el PATH global de Windows. Se pueden usar scripts Node.js con Playwright para la conversión PDF/HTML a PNG y comparación visual.
