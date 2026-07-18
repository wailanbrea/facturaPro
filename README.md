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

## Numeracion por Perfil y Logo

La numeracion actual funciona por:

```text
perfil fiscal + logo + tipo de documento + usuario
```

Debe existir una configuracion explicita para cada combinacion usada:

- Perfil fiscal.
- Logo.
- Usuario que factura.
- Tipo de documento: `invoice` o `quotation`.
- Prefijo: `FAC-` o `PRES-`.
- Serie: ejemplo `PA-AIR`, `PA-CAL`, `PA-TEC`, `LA-AIR`, `LR-CAL`, `LA-TEC`.
- Proximo numero.
- Longitud.

Regla importante:

- El sistema ya no crea series automaticamente por cambiar o previsualizar logos.
- Si se intenta emitir con un logo sin numeracion configurada, devuelve error.
- La opcion de crear factura/presupuesto/informe sin logo fue removida cuando el perfil tiene logos.

Series operativas actuales:

```text
PA: AIR, CAL, TEC
LR/LA: AIR, CAL, TEC
```

Ejemplo:

```text
Perfil: PAMELA MISHELL AVILA CELI
Logo: Aire Acondicionado
Tipo: Factura
Prefijo: FAC-
Serie: PA-AIR
Proximo numero: 1
Longitud: 6
Resultado: FAC-PA-AIR-000001
```

Para presupuesto del mismo logo:

```text
Tipo: Presupuesto
Prefijo: PRES-
Serie: PA-AIR
Resultado: PRES-PA-AIR-000001
```

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

La app debe enviar `logo_path` al crear o actualizar facturas, presupuestos e informes.

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
