# FASE 15 - Produccion

Fecha: 2026-05-21

> Para desplegar realmente en un VPS, usar el runbook autosuficiente y actualizado:
> **`docs/DEPLOY-VPS.md`** (incluye autenticidad de facturas, QR/verificación, HTTPS,
> firewall y configuración Android release/debug). Este documento es histórico.

## Objetivo

Preparar el proyecto para despliegue en Windows VPS con Apache/XAMPP, dejando configuracion, scripts y contratos operativos listos antes de la validacion final en infraestructura real.

## Alcance real de esta fase

Esta fase no puede cerrarse completamente sin:

- VPS o servidor objetivo
- dominio o subdominio publico
- credenciales MySQL de produccion
- endpoint publico real para Android

Sin esos datos, lo correcto es dejar la infraestructura de despliegue preparada y documentada, pero no marcar como completadas las pruebas en servidor ni la validacion Android contra API publica.

## Implementacion

### Backend Laravel

Se agrego plantilla de entorno productivo:

- `backend/.env.production.example`

Puntos clave:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `FILESYSTEM_DISK=public`
- logs diarios con `LOG_STACK=daily`
- cookie segura para sesiones
- `CHROME_PATH` explicito para generacion PDF en Windows

Se agrego VirtualHost Apache/XAMPP:

- `backend/deploy/apache/facturapro-vhost.conf`

Ese VirtualHost apunta correctamente a:

```text
backend/public
```

No al directorio raiz del proyecto. Cualquier despliegue que apunte fuera de `public` seria una mala configuracion de seguridad.

Se agregaron scripts operativos:

- `backend/scripts/post-deploy.ps1`
- `backend/scripts/backup-database.ps1`
- `backend/scripts/smoke-test-production.ps1`

`post-deploy.ps1` ejecuta:

- `artisan down`
- `artisan migrate --force`
- `artisan storage:link`
- `artisan config:cache`
- `artisan route:cache`
- `artisan view:cache`
- `artisan up`

`backup-database.ps1` usa `mysqldump` con:

- `--single-transaction`
- `--quick`
- `--routines`
- `--events`

Eso evita un backup pobre o inconsistente para una base transaccional.

`smoke-test-production.ps1` permite validar rapidamente un backend publico:

- `/api/health`
- login
- `/api/me`
- `/api/settings/bootstrap`
- logout

### Android

Habia un defecto de despliegue: la app tenia `API_BASE_URL` fija para emulador.

Se corrigio en:

- `android/app/build.gradle.kts`

Ahora:

- `debug` usa `FACTURAPRO_API_BASE_URL_DEBUG`
- `release` usa `FACTURAPRO_API_BASE_URL_RELEASE`

Se agrego:

- `android/gradle.properties.example`

Con eso ya no hace falta tocar codigo para cambiar entre entorno local y API publica.

## Permisos y almacenamiento

En Windows/XAMPP, las carpetas que deben quedar con escritura para el usuario del servicio o del proceso Apache/PHP son:

- `backend/storage`
- `backend/bootstrap/cache`

La politica correcta es otorgar modificacion solo a esas rutas, no al proyecto completo.

Tambien debe existir:

```text
php artisan storage:link
```

Sin ese enlace, la descarga de PDF desde `public/storage` no funcionara correctamente.

## Logs

La configuracion propuesta para produccion usa rotacion diaria:

```text
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30
```

Eso reduce crecimiento infinito del archivo unico y evita mantener `debug` abierto en produccion.

## Validacion ejecutada

Android:

```text
.\gradlew.bat :app:assembleDebug
.\gradlew.bat :app:lintDebug
```

Resultado:

```text
BUILD SUCCESSFUL
```

Scripts PowerShell:

- parse correcto de `backup-database.ps1`
- parse correcto de `post-deploy.ps1`

## Validaciones pendientes en servidor real

Estas no se ejecutaron porque no existe VPS/dominio publico definido en el contexto actual:

1. copiar `.env.production.example` a `.env.production` y completar secretos reales
2. crear base de datos y usuario de produccion
3. activar VirtualHost en Apache
4. correr `post-deploy.ps1`
5. confirmar `GET /api/health`
6. emitir factura real y generar PDF en el servidor
7. probar login Android apuntando a `FACTURAPRO_API_BASE_URL_RELEASE`
8. probar creacion de factura Android contra API publica

## Checklist operativo

Se agrego una checklist separada para salida:

- `docs/CHECKLIST-SALIDA-PRODUCCION.md`

## Riesgos pendientes

- `CHROME_PATH` debe coincidir con la ruta real de Chrome/Edge en el VPS. Si no, el PDF fallara aunque la app este correcta.
- La fase no incluye HTTPS real. Para produccion publica, `APP_URL`, cookies seguras y Android release deben operar sobre HTTPS.
- No se configuro programacion automatica de backups ni retencion externa; solo se dejo el script base.
