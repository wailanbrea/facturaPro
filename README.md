# FacturaPro

Sistema Laravel + app Android para facturacion, presupuestos, informes tecnicos, pagos, clientes, perfiles fiscales, logos por perfil y verificacion de documentos por codigo/QR.

## Estructura

- `backend/`: aplicacion Laravel 12, API, panel web, generacion de PDF y base de datos.
- `android/`: aplicacion Android que consume la API del backend.
- `backend/deploy/apache/facturapro-vhost.conf`: ejemplo de virtual host Apache.

## Requisitos

- PHP 8.2 o superior.
- Composer.
- Node.js y npm para compilar assets web.
- MySQL/MariaDB.
- Apache con `mod_rewrite`.
- Google Chrome, Microsoft Edge o Chromium para generar PDF.
- Android Studio/JDK para compilar la app Android.

En XAMPP, el proyecto actual esta pensado para:

```text
C:\xampp\htdocs\facturaPro
```

El dominio configurado en produccion es:

```text
https://facturapro.bsolutions.dev
```

## Despliegue Backend

1. Clonar o actualizar el codigo:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/wailanbrea/facturaPro.git
cd C:\xampp\htdocs\facturaPro
git pull origin master
```

2. Instalar dependencias PHP:

```powershell
cd C:\xampp\htdocs\facturaPro\backend
composer install --no-dev --optimize-autoloader
```

3. Instalar y compilar assets:

```powershell
npm install
npm run build
```

4. Crear `.env`:

```powershell
copy .env.production.example .env
```

Valores minimos para `facturapro.bsolutions.dev`:

```env
APP_NAME=FacturaPro
APP_ENV=production
APP_DEBUG=false
APP_URL=https://facturapro.bsolutions.dev

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=facturapro
DB_USERNAME=usuario_mysql
DB_PASSWORD=clave_mysql

SESSION_DRIVER=database
SESSION_DOMAIN=facturapro.bsolutions.dev
SESSION_SECURE_COOKIE=true
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
CACHE_STORE=database

CHROME_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe
INVOICE_VERIFICATION_URL=https://facturapro.bsolutions.dev/invoices/verify
```

Generar claves:

```powershell
php artisan key:generate
php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
```

Colocar el valor generado por el segundo comando en:

```env
INVOICE_SIGNING_KEY=valor_generado
```

Importante: no cambiar `INVOICE_SIGNING_KEY` despues de emitir documentos, porque invalida las firmas anteriores.

5. Migrar y sembrar:

```powershell
php artisan migrate --force
php artisan db:seed --force
```

Si ya hay datos reales, no ejecutar `db:seed` sin revisar antes.

6. Crear enlace de storage:

```powershell
php artisan storage:link
```

7. Optimizar Laravel:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

8. Permisos necesarios:

Apache/PHP debe poder escribir en:

```text
backend/storage
backend/bootstrap/cache
```

## Apache / Virtual Host

El `DocumentRoot` debe apuntar a `backend/public`, no a la raiz del repositorio.

Ejemplo para XAMPP:

```apache
<VirtualHost *:80>
    ServerName facturapro.bsolutions.dev
    DocumentRoot "C:/xampp/htdocs/facturaPro/backend/public"

    <Directory "C:/xampp/htdocs/facturaPro/backend/public">
        AllowOverride All
        Require all granted
        Options FollowSymLinks
        DirectoryIndex index.php
    </Directory>

    ErrorLog "logs/facturapro-error.log"
    CustomLog "logs/facturapro-access.log" combined
</VirtualHost>
```

Activar `mod_rewrite`, reiniciar Apache y apuntar el DNS del subdominio al servidor.

Para HTTPS, configurar certificado SSL en Apache y mantener:

```env
APP_URL=https://facturapro.bsolutions.dev
SESSION_SECURE_COOKIE=true
```

## PDF y Chrome

Facturas, presupuestos e informes se generan con Chrome/Chromium headless. Si falla la generacion de PDF, verificar:

```env
CHROME_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe
```

En Linux puede ser:

```env
CHROME_PATH=/usr/bin/chromium
```

## Plantillas PDF

Cada tipo de documento tiene su plantilla, en A4 apaisado a dos paginas:

```text
backend/resources/views/pdf/invoice.blade.php     factura
backend/resources/views/pdf/quotation.blade.php   presupuesto
backend/resources/views/pdf/report.blade.php      informe tecnico
```

Las tres comparten `backend/resources/views/pdf/partials/`: `styles.blade.php`
concentra todo el CSS y los parciales (`doc-heading`, `issuer-card`,
`client-card`, `items-table`, `bank-transfer`, `legal-grid`, `watermark`,
`eco-notice`) son los bloques reutilizados. `App\Support\PdfDocumentContext`
prepara moneda, logo embebido, QR, marca de agua y el reparto de lineas entre
hojas; vive en PHP y no en un `@php` de Blade para poder testearlo.

### Reglas de diseno

Zona prohibida en los disenos actuales, cubierta por tests:

- Bizum, tarjeta y efectivo. La unica forma de pago impresa es transferencia.
- Telefono, email y web del emisor en la tarjeta fiscal.
- Columna "Categoria" en la tabla de lineas.
- Copias `ORIGINAL: CLIENTE` / `COPIA: VENDEDOR`.
- Recursos remotos: fuentes, CDN o `<script>`. Todo va embebido en `data:`.
- Glifos Unicode decorativos. Los iconos son SVG en linea (`x-pdf-icon`).

Convencion: toda etiqueta en negrita termina en dos puntos. Un test recorre por
regex todos los `<dt>` del HTML generado y falla si alguno no lo cumple.

### Donde vive cada texto

| Bloque | Origen | Editable |
| --- | --- | --- |
| Aceptacion de la intervencion | `invoices.conformity_text`, por defecto desde Configuracion -> Textos legales | Si, tras pulsar "Habilitar edicion" |
| Texto legal | `invoices.legal_text`, mismo origen | Si, tras desbloquear |
| Observaciones | `invoices.observations` | Si, tras desbloquear |
| Condiciones (factura) | Plantilla | No |
| Aceptacion del presupuesto | Plantilla | No |
| Condiciones generales (pagina 2) | Array `$legalBlocks` en cada plantilla | No |
| Forma de pago | Plantilla; la cuenta bancaria si se elige | Solo la cuenta |

Los tres campos editables llegan bloqueados al abrir el formulario y exigen
pulsar "Habilitar edicion". El backend descarta del payload lo que no venga
desbloqueado, asi que el candado no depende del navegador. Ademas,
Configuracion -> Campos bloqueados permite un candado firme que el boton no
abre para usuarios sin permiso `configurar_sistema`.

Aviso: los bloques de `$legalBlocks` son un borrador de trabajo redactado para
dimensionar la maqueta. No tienen revision juridica.

### Snapshot del cliente

`invoices` guarda `client_name`, `client_tax_id`, `client_address`,
`client_city`, `client_email` y `client_phone` en el momento de crear el
documento. Editar despues la ficha del cliente no altera un PDF ya emitido.
Antes de la migracion `2026_08_08_000000_add_client_contact_snapshot_to_invoices`
el correo y el telefono se leian en vivo de `clients`, con lo que un cambio de
contacto reescribia facturas antiguas.

### Garantia

El bloque "Garantia legal" usa `Warranty::durationLabelFor()`, que dice los
multiplos de doce en anos: 6 meses, 1 ano, 3 anos. Imprimir "36 meses" donde se
vendio "3 anos" confunde al cliente aunque sea el mismo plazo.

## Verificacion de Documentos

Las facturas e informes emitidos quedan firmados con codigo de seguridad y QR. La verificacion publica usa:

```text
https://facturapro.bsolutions.dev/invoices/verify
```

Comandos utiles:

```powershell
php artisan invoices:sign-existing --dry-run
php artisan invoices:sign-existing
php artisan invoices:verify-chain
```

`invoices:sign-existing` solo debe usarse cuando ya esta definido `INVOICE_SIGNING_KEY`.

## Numeracion por Perfil Fiscal

La numeracion funciona por:

```text
perfil fiscal + tipo de documento
```

El logo y el usuario ya no intervienen. La migracion
`2026_07_21_000000_scope_invoice_numbers_by_fiscal_profile` elimino las columnas
`user_id` y `logo_path` de `invoice_number_settings` y dejo una clave unica sobre
`fiscal_profile_id + document_type`. Cambiar el logo de una factura no abre una
serie nueva.

Cada serie se administra en Configuracion -> Numeracion con:

- Perfil fiscal (o global, sin perfil).
- Tipo de documento: `invoice` o `quotation`.
- Prefijo: `FAC-` o `PRES-`.
- Serie: opcional, se intercala en el numero.
- Proximo numero.
- Longitud (ceros a la izquierda).
- Reinicio anual o mensual.

Ejemplo:

```text
Perfil: PAMELA MISHELL AVILA CELI
Tipo: Factura
Prefijo: FAC-
Serie: PA
Proximo numero: 1
Longitud: 6
Resultado: FAC-PA-000001
```

Con dos perfiles fiscales y dos tipos de documento salen cuatro series
independientes, que es la configuracion prevista para PAMELA y LUIS A.

## App Android

La app consume:

```text
https://facturapro.bsolutions.dev/api/
```

Configurar `android/gradle.properties` desde el ejemplo:

```powershell
copy android\gradle.properties.example android\gradle.properties
```

Valor de produccion:

```properties
FACTURAPRO_API_BASE_URL_RELEASE=https://facturapro.bsolutions.dev/api/
```

El endpoint de bootstrap entrega catalogos, perfiles, logos y previsualizaciones de numeracion:

```text
GET /api/settings/bootstrap
```

El bootstrap tambien entrega los permisos efectivos del usuario y los campos de
factura bloqueados. Android usa esos datos para mostrar solamente los modulos y
acciones autorizados, igual que el panel web.

La aplicacion movil cubre el flujo operativo de clientes, calendario, reportes
financieros, informes tecnicos, facturas y presupuestos. En documentos permite
crear, editar, emitir, anular, convertir presupuestos, registrar pagos, generar,
visualizar, imprimir y compartir PDF, ademas de verificar el QR.

Los catalogos, perfiles fiscales, numeraciones, usuarios, roles y auditoria se
administran en el panel web. Android consume esa configuracion central y debe
enviar `logo_path` al crear o actualizar facturas, presupuestos e informes.

## Comandos de Mantenimiento

Despues de cambios de codigo:

```powershell
git pull origin master
cd backend
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

En Windows/XAMPP, ejecutar el script de post-deploy desde PowerShell abierto
como Administrador:

```powershell
cd C:\xampp\htdocs\facturaPro
.\backend\scripts\post-deploy.ps1
```

El script usa por defecto `C:\xampp\htdocs\facturaPro\backend` y purga
`bootstrap/cache/packages.php` y `bootstrap/cache/services.php` antes de llamar
a `artisan`. Esto evita el 500 completo por `Laravel\Pail\PailServiceProvider`
cuando se hizo `composer install --no-dev`.

Para limpiar cache durante diagnostico:

```powershell
php artisan optimize:clear
php artisan config:cache
```

Siempre volver a ejecutar `config:cache` despues de limpiar configuracion en produccion.

## Credencial Sembrada

Solo para instalacion inicial o entorno de prueba:

```text
Email: admin@facturapro.local
Password: FacturaPro123!
```

Cambiar esa clave despues del primer acceso en produccion.

## Pruebas

Backend:

```powershell
cd backend
php artisan test
```

Pruebas enfocadas usadas para numeracion y PDF:

```powershell
php artisan test tests\Feature\Services\InvoiceNumberServiceTest.php
php artisan test --filter "authenticated_user_can_issue_and_convert_quotation_without_payment_state|generate_and_download_pdf_endpoint"
```

Android:

```powershell
cd android
.\gradlew test
```

Requiere JDK configurado con `JAVA_HOME`.

## Archivos que No Deben Versionarse

No subir:

- `backend/.env`
- `backend/vendor/`
- `backend/node_modules/`
- `backend/storage/app/public/*` con archivos reales de clientes
- `android/local.properties`
- PDFs o imagenes temporales de revision
