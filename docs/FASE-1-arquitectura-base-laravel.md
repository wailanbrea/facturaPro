# FASE 1 - Arquitectura base Laravel

## Objetivo

Crear una base Laravel 12 separada de los archivos de referencia y preparada para API, panel web, MySQL y desarrollo modular.

## Decisiones aplicadas

- Laravel vive en `backend/`.
- La raiz del proyecto conserva documentacion, pantallas de referencia, Excel oficial y proyecto Android.
- `.env` usa MySQL local:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=facturapro
DB_USERNAME=root
DB_PASSWORD=
```

- Zona horaria configurada con `APP_TIMEZONE=America/Santo_Domingo`.
- Locale base configurado como `es`.
- Sanctum instalado para API movil.
- `App\Models\User` usa `Laravel\Sanctum\HasApiTokens`.

## Archivos principales creados o modificados

- `backend/`
- `backend/.env`
- `backend/.env.example`
- `backend/app/Models/User.php`
- `backend/config/app.php`
- `backend/routes/api.php`
- `backend/app/Services/.gitkeep`
- `backend/app/DTOs/.gitkeep`
- `backend/app/Http/Requests/Api/.gitkeep`
- `backend/app/Http/Resources/Api/.gitkeep`

## Ruta de salud

```http
GET /api/health
```

Respuesta validada:

```json
{"status":"ok","app":"FacturaPro","environment":"local"}
```

## Comandos ejecutados

```bash
composer create-project laravel/laravel:^12.0 backend --no-interaction
php artisan install:api --no-interaction
php artisan config:clear
php artisan migrate
php artisan storage:link
php artisan test
```

## Validacion

- Laravel Framework: `12.60.2`.
- PHP: `8.2.12`.
- Composer: `2.8.11`.
- MySQL accesible por PDO en `127.0.0.1:3306`.
- Base `facturapro` creada si no existia.
- Migraciones iniciales ejecutadas en MySQL.
- Sanctum creo tabla `personal_access_tokens`.
- `php artisan test`: 2 tests pasados.
- Servidor local iniciado en `http://127.0.0.1:8000`.

## Riesgos pendientes

- La configuracion actual asume MySQL local `root` sin password, comun en XAMPP local. En produccion debe reemplazarse por usuario dedicado con password fuerte y permisos minimos.
- Aun no existen migraciones de dominio de facturacion. Eso corresponde a FASE 2.
- Aun no existe autenticacion real de login API ni panel web. Eso corresponde a fases posteriores.

## Resultado

FASE 1 completada. La siguiente fase es crear migraciones de dominio: monedas, impuestos, terminos, garantias, cuentas, perfiles fiscales, facturas, items, pagos, roles, permisos, logs y settings.
