# Checklist de salida a produccion

> Runbook completo paso a paso (apto para agente IA): **`docs/DEPLOY-VPS.md`**.
> Este archivo es la lista de verificación resumida.

## 1. Infraestructura

- VPS Windows operativo
- Apache/XAMPP instalado
- PHP compatible con Laravel 12
- MySQL operativo
- Chrome o Edge instalado para PDF
- dominio o subdominio apuntando al servidor
- HTTPS configurado

## 2. Backend

- copiar `backend/.env.production.example` a `.env`
- completar `APP_KEY` (`php artisan key:generate`)
- completar credenciales reales de MySQL
- completar `APP_URL`
- completar `SESSION_DOMAIN`
- **generar y completar `INVOICE_SIGNING_KEY`** (`php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"`) — antes de emitir o firmar facturas
- completar `INVOICE_VERIFICATION_URL` con el dominio público (`https://.../invoices/verify`)
- confirmar `APP_DEBUG=false`
- confirmar `CHROME_PATH` (en Linux suele ser `/usr/bin/chromium`)
- ejecutar `composer install --no-dev --optimize-autoloader`
- ejecutar `php artisan migrate --force`
- ejecutar `php artisan db:seed --force` (solo primer despliegue, datos base/roles/permisos)
- (solo si se migran facturas ya emitidas) ejecutar `php artisan invoices:sign-existing`
- ejecutar `php artisan storage:link`
- ejecutar `php artisan config:cache`
- ejecutar `php artisan route:cache`
- ejecutar `php artisan view:cache`

## 3. Apache

- registrar `backend/deploy/apache/facturapro-vhost.conf`
- confirmar que `DocumentRoot` apunta a `backend/public`
- confirmar `AllowOverride All`
- reiniciar Apache

## 4. Archivos y permisos

- escritura en `backend/storage`
- escritura en `backend/bootstrap/cache`
- sin permisos de escritura global sobre todo el proyecto

## 5. Base de datos y backup

- crear usuario MySQL dedicado
- validar conexion desde Laravel
- ejecutar `backend/scripts/backup-database.ps1`
- programar tarea automatica de backup
- validar restauracion fuera de la base en uso

## 6. Smoke test backend

Ejecutar:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\scripts\smoke-test-production.ps1 `
  -ApiBaseUrl "https://api.example.com/api" `
  -Email "admin@facturapro.local" `
  -Password "FacturaPro123!"
```

Debe validar:

- `/api/health`
- login
- `/api/me`
- `/api/settings/bootstrap`
- logout

## 6.1 Autenticidad de facturas

- confirmar `INVOICE_SIGNING_KEY` definido y guardado en gestor de secretos (no en la BD)
- emitir una factura y confirmar que el PDF muestra QR + código + sello "DOCUMENTO ORIGINAL"
- abrir `/invoices/verify?number=...&code=...` (o el menú "Verificar documento") y confirmar "auténtico"
- ejecutar `php artisan invoices:verify-chain` → cadena íntegra
- programar `invoices:verify-chain` periódico (cron/tarea) para auditoría continua

## 7. PDF en servidor

- iniciar sesion web
- crear factura borrador
- emitir factura
- generar PDF
- descargar PDF
- confirmar archivo en `storage/app/public/invoices`
- confirmar que el PDF abre y renderiza ambas paginas

## 8. Android release

- definir `FACTURAPRO_API_BASE_URL_RELEASE` con la **API pública HTTPS** (el build release solo permite HTTPS; HTTP queda bloqueado por `network_security_config`)
- definir `INVOICE_VERIFICATION_URL` en el backend con el dominio público (el QR debe resolver desde el móvil)
- generar build release apuntando a API publica
- validar login real
- validar bootstrap (la pantalla Configuración carga catálogos sin error de red)
- validar crear factura
- validar preview
- validar generar y compartir PDF
- validar escaneo de QR en "Verificar documento" (requiere Google Play Services en el dispositivo)

## 9. Seguridad minima antes de salir

- cambiar password temporal del admin
- revisar que `.env` no sea publico
- revisar que `APP_DEBUG=false`
- revisar cookies seguras
- revisar que no exista acceso directo fuera de `public`
- revisar que logs no expongan secretos
