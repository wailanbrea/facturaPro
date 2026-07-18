# Guía de despliegue en VPS — FacturaPro

> **Para quién es esto:** runbook operativo (apto para un agente IA o un operador) para
> desplegar FacturaPro en un VPS desde cero. Es **autosuficiente**: contiene todos los
> datos y comandos necesarios. Complementa a `CHECKLIST-SALIDA-PRODUCCION.md` (lista de
> verificación) y `FASE-15-produccion.md` (notas históricas, enfocadas en Windows/XAMPP).
>
> Última actualización: 2026-05-29.

---

## 0. Arquitectura del sistema

- **Backend:** Laravel 12 + Sanctum. Sirve **dos cosas a la vez**:
  - API REST bajo `/api/*` (consumida por la app Android).
  - Panel web administrativo (Blade) bajo `/*`.
- **Base de datos:** MySQL 8 (transaccional; montos con `decimal`).
- **PDF:** se renderiza con **Chrome/Chromium headless** (no hay librería PHP de PDF).
  El binario se invoca desde `InvoicePdfService` / `ReportPdfService`.
- **QR de autenticidad:** `chillerlan/php-qrcode` (vía Composer; salida SVG, **no requiere
  ext-gd**).
- **Cliente móvil:** app Android (Jetpack Compose) que apunta a la API pública por HTTPS.

El "documento original" de una factura **es el registro firmado en la base de datos**, no
el PDF. Ver la sección 12 (autenticidad) — es crítica y específica de este sistema.

---

## 1. Datos que el operador debe tener ANTES de empezar

| Dato | Ejemplo | Notas |
|---|---|---|
| Dominio/subdominio público | `billing.example.com` | Debe apuntar (A/AAAA) a la IP del VPS |
| Acceso SSH al VPS | `root@1.2.3.4` | Con sudo |
| Credenciales MySQL de producción | usuario + password dedicados | No usar `root` para la app |
| Correo SMTP (opcional) | host/usuario/clave | Para notificaciones |
| URL pública de la API para Android | `https://billing.example.com/api/` | Va en el build release |

---

## 2. Prerrequisitos del sistema (Ubuntu/Debian)

```bash
sudo apt update
# PHP 8.2+ con las extensiones que usa Laravel 12 + este proyecto
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd \
  mysql-server nginx git unzip chromium-browser

# Composer
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Notas:
- `php-bcmath` es necesario para los cálculos monetarios (`Brick\Math`).
- `chromium-browser` provee el motor de PDF. En algunas distros el binario es
  `/usr/bin/chromium` o `/usr/bin/google-chrome`. Anótalo para `CHROME_PATH`.
- `php-gd` es opcional (el QR se genera en SVG), pero conviene tenerlo.

---

## 3. Obtener el código

```bash
sudo mkdir -p /var/www/facturapro
sudo chown "$USER" /var/www/facturapro
git clone <REPO_URL> /var/www/facturapro      # o copiar el árbol del proyecto
cd /var/www/facturapro/backend
composer install --no-dev --optimize-autoloader
```

> El **DocumentRoot del servidor web debe ser `backend/public`**, nunca la raíz del
> proyecto (exponer el resto es un fallo de seguridad).

---

## 4. Configurar el entorno (`.env`)

```bash
cd /var/www/facturapro/backend
cp .env.production.example .env
php artisan key:generate          # genera APP_KEY
```

Edita `.env` y completa **como mínimo**:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.example.com

DB_DATABASE=facturapro
DB_USERNAME=facturapro_user
DB_PASSWORD=<password real>

SESSION_DOMAIN=billing.example.com
SESSION_SECURE_COOKIE=true

# Motor de PDF (ruta real del binario en el VPS)
CHROME_PATH=/usr/bin/chromium

# AUTENTICIDAD DE FACTURAS — ver sección 12 (OBLIGATORIO)
INVOICE_SIGNING_KEY=<clave secreta generada>
INVOICE_VERIFICATION_URL=https://billing.example.com/invoices/verify
```

Genera la clave de firma:

```bash
php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
```

Pega el resultado en `INVOICE_SIGNING_KEY`. **Guárdala también en tu gestor de secretos.**

---

## 5. Base de datos

```bash
sudo mysql -e "CREATE DATABASE facturapro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'facturapro_user'@'localhost' IDENTIFIED BY '<password real>';"
sudo mysql -e "GRANT ALL PRIVILEGES ON facturapro.* TO 'facturapro_user'@'localhost'; FLUSH PRIVILEGES;"

cd /var/www/facturapro/backend
php artisan migrate --force
php artisan db:seed --force        # SOLO primer despliegue: roles, permisos, catálogos, admin
```

> El usuario admin sembrado es `admin@facturapro.local` / `FacturaPro123!`.
> **Cambia esta contraseña inmediatamente tras el primer login.**

Si **migras facturas ya emitidas** desde otro entorno (que aún no tienen firma),
séllalas en la cadena de autenticidad una sola vez:

```bash
php artisan invoices:sign-existing
```

---

## 6. Almacenamiento, permisos y cachés

```bash
cd /var/www/facturapro/backend
php artisan storage:link           # enlaza public/storage (necesario para descargar PDFs)

# Escritura solo en las rutas que la requieren (no en todo el proyecto)
sudo chown -R www-data:www-data storage bootstrap/cache public/storage
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 7. Servidor web (Nginx + PHP-FPM)

`/etc/nginx/sites-available/facturapro`:

```nginx
server {
    listen 80;
    server_name billing.example.com;
    root /var/www/facturapro/backend/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 20M;   # margen para subidas/respuestas
}
```

```bash
sudo ln -s /etc/nginx/sites-available/facturapro /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> En Windows/XAMPP usa en su lugar `backend/deploy/apache/facturapro-vhost.conf`
> (apunta a `backend/public`, `AllowOverride All`).

### HTTPS (obligatorio para Android release)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d billing.example.com
```

El build **release** de Android solo permite HTTPS (ver sección 14), así que la API
**debe** servirse por HTTPS antes de probar la app de producción.

---

## 8. Procesos en background (colas y programador)

La app usa `QUEUE_CONNECTION=database` y el throttle de login usa el rate limiter (que
funciona con `CACHE_STORE=database` por defecto — no requiere Redis).

Worker de colas (systemd) `/etc/systemd/system/facturapro-queue.service`:

```ini
[Unit]
Description=FacturaPro queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
WorkingDirectory=/var/www/facturapro/backend
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now facturapro-queue
```

Programador de Laravel + auditoría de la cadena de facturas (crontab de `www-data`):

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/facturapro/backend && php artisan schedule:run >> /dev/null 2>&1
# Auditoría de integridad de la cadena de autenticidad (diaria, 02:00)
0 2 * * * cd /var/www/facturapro/backend && php artisan invoices:verify-chain >> storage/logs/chain-audit.log 2>&1
```

---

## 9. Firewall y endurecimiento de red

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'      # 80 + 443
sudo ufw enable
```

Reglas clave:
- **Solo** exponer 80/443 (y SSH). Todo lo demás cerrado.
- **MySQL** debe escuchar solo en `127.0.0.1` (no exponer 3306).
- **Nunca** usar `php artisan serve` como servidor de producción ni exponer el puerto 8000.
  `artisan serve` es solo para desarrollo local (ver Apéndice A).
- `INVOICE_SIGNING_KEY` y `.env` nunca deben ser accesibles por web (quedan fuera de
  `public/`, lo cual ya está garantizado si el DocumentRoot es `backend/public`).

---

## 10. Verificación post-despliegue (smoke test)

```bash
BASE=https://billing.example.com

# 1) Salud
curl -s $BASE/api/health        # → {"status":"ok",...}

# 2) Login → token
TOKEN=$(curl -s -X POST $BASE/api/login -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@facturapro.local","password":"FacturaPro123!"}' \
  | php -r 'echo json_decode(file_get_contents("php://stdin"),true)["access_token"];')

# 3) Perfil y catálogos
curl -s $BASE/api/me              -H "Authorization: Bearer $TOKEN" | head -c 200
curl -s $BASE/api/settings/bootstrap -H "Authorization: Bearer $TOKEN" | head -c 200
```

Luego, en el panel web:
1. Iniciar sesión → crear factura borrador → **emitir** → **generar PDF** → descargar.
2. Confirmar que el PDF abre y muestra **QR + código + sello "DOCUMENTO ORIGINAL"**.
3. Ir a **"Verificar documento"**, escribir número + código → debe decir **auténtico**.
4. `php artisan invoices:verify-chain` → "la cadena está íntegra".

---

## 11. Backups

- Script base: `backend/scripts/backup-database.ps1` (Windows). En Linux, equivalente:

```bash
mysqldump --single-transaction --quick --routines --events \
  -u facturapro_user -p facturapro > /var/backups/facturapro-$(date +%F).sql
```

- Programar diariamente (cron) y validar restauración fuera de la base en uso.
- **Incluir en el backup/registro seguro el valor de `INVOICE_SIGNING_KEY`**: sin esa
  clave no se pueden re-verificar las facturas existentes (ver sección 12).

---

## 12. Autenticidad de facturas (CRÍTICO)

El sistema firma cada factura al **emitirla** con `HMAC-SHA256` sobre sus campos
inmutables, encadenando la firma de la factura anterior (cadena a prueba de manipulación),
y guarda el `SHA-256` del PDF. Imprime un QR + código en el documento, verificables
internamente.

Reglas de despliegue **obligatorias**:

1. **`INVOICE_SIGNING_KEY` debe estar definido ANTES de emitir o firmar facturas.**
   Si no se define, el sistema deriva una clave de `APP_KEY` (funciona, pero entonces
   `APP_KEY` queda atada a la validez de las firmas). En producción usa una clave dedicada.
2. **La clave vive solo en el servidor (`.env`/gestor de secretos), nunca en la BD.**
   Así, un volcado de la base de datos no basta para falsificar facturas.
3. **Cambiar la clave invalida TODAS las firmas existentes** (la verificación pasará a
   reportar "alterado"). Rotarla = evento de re-firma planificado. Trátala como secreto
   de larga vida y respáldala.
4. `INVOICE_VERIFICATION_URL` debe ser el **dominio público** (`https://.../invoices/verify`)
   para que el QR resuelva desde cualquier dispositivo.
5. Backfill de datos preexistentes: `php artisan invoices:sign-existing` (una vez).
6. Auditoría continua: `php artisan invoices:verify-chain` (cron diario, sección 8).

Rutas de verificación (ambas internas, tras autenticación con permiso `ver_factura`):
- Web: `GET /invoices/verify?number=...&code=...`
- API: `GET /api/invoices/verify?number=...&code=...` (la usa el escáner de la app).

---

## 13. App Android (build release)

El build release apunta a la API pública y **solo permite HTTPS** (la config de seguridad
de red de `src/main` prohíbe tráfico en claro; el cleartext solo se permite en `debug`).

1. Definir la URL pública de la API en `android/gradle.properties` (o variable de entorno):

```properties
FACTURAPRO_API_BASE_URL_RELEASE=https://billing.example.com/api/
```

2. Asegurar que `INVOICE_VERIFICATION_URL` del backend usa el mismo dominio público
   (el QR escaneado debe resolver).
3. Generar el build: `./gradlew :app:assembleRelease` (firmar con la keystore del proyecto).
4. El **escaneo de QR** usa Google Code Scanner (ML Kit): requiere **Google Play Services**
   en el dispositivo. Gestiona cámara/permiso por sí mismo (no hay permiso CAMERA en el
   manifest ni CameraX).
5. Validar en el dispositivo: login, **Configuración** (carga catálogos sin error de red),
   crear/preview/PDF, y "Verificar documento" (escaneo).

---

## Apéndice A — Pruebas locales (LAN) contra un dispositivo físico

Para probar la app **debug** contra un backend de desarrollo en tu PC y un teléfono real:

1. **Arrancar el servidor escuchando en todas las interfaces** (no solo loopback):

   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Apuntar la app a la IP LAN del PC** (no a `10.0.2.2`, que solo existe en el emulador).
   En `android/gradle.properties`:

   ```properties
   FACTURAPRO_API_BASE_URL_DEBUG=http://192.168.X.Y:8000/api/
   ```

   (sustituye por la IP LAN real del PC: `ipconfig` / `ip a`).

3. **Cleartext HTTP en debug:** ya está permitido por
   `android/app/src/debug/res/xml/network_security_config.xml` (solo en debug). El build
   release sigue siendo HTTPS-only.

4. **Firewall de Windows:** permitir el puerto 8000 entrante si el teléfono no conecta.

5. **Instalar por adb inalámbrico:** el APK queda en
   `app/build/intermediates/apk/debug/app-debug.apk` y está marcado *test-only*, así que
   instálalo con `-t`:

   ```bash
   adb install -r -t app/build/intermediates/apk/debug/app-debug.apk
   ```

---

## Apéndice B — Resolución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| App: "falla la red/API" al entrar a **Configuración** | El bootstrap (`/api/settings/bootstrap`) no conecta; Configuración es la única pantalla que muestra ese error | Verificar `API_BASE_URL` (HTTPS público en release; IP LAN en debug), HTTPS válido, y que el dispositivo alcance el host |
| Todo falla en un dispositivo **físico** pero funciona en emulador | La URL usa `10.0.2.2` (solo emulador) | Usar la IP LAN del PC (Apéndice A) o el dominio público |
| HTTP bloqueado en release | `network_security_config` de `main` prohíbe cleartext | Servir la API por **HTTPS** (sección 7) |
| PDF no se genera (500) | `CHROME_PATH` incorrecto o Chromium ausente | Instalar `chromium-browser` y fijar la ruta real en `.env` |
| Verificación dice "alterado" en facturas válidas | `INVOICE_SIGNING_KEY` (o `APP_KEY` sin clave dedicada) cambió tras firmar | Restaurar la clave original; si se rotó a propósito, re-firmar |
| Descarga de PDF 404 desde web | Falta `php artisan storage:link` | Ejecutarlo y verificar permisos de `public/storage` |
| Login responde 429 | Throttle de fuerza bruta (5/min por email+IP) | Esperar 1 min; es comportamiento esperado |
| **Todo el sitio y el API responden 500** (incluso `/api/health`) y la consola dice `Class "Laravel\Pail\PailServiceProvider" not found` | `composer install --no-dev` desinstalo los paquetes de desarrollo, pero `bootstrap/cache/packages.php` sigue registrando sus providers. Laravel no arranca, asi que ni `artisan` funciona | Borrar las caches a mano (no con artisan) y regenerarlas: ver [seccion 12](#12-actualizacion-redeploy) |

---

## 12. Actualización (redeploy)

Para publicar cambios en un servidor ya aprovisionado. **El orden importa**: las
cachés compiladas se purgan *antes* de tocar las dependencias, porque si quedan
apuntando a un paquete de desarrollo ya desinstalado, Laravel deja de arrancar y
ningún comando `artisan` funciona.

```bash
cd /var/www/facturapro
php artisan --working-dir=backend down || true   # tolera que la app ya este caida
git pull origin master
cd backend

# 1. Purga de caches compiladas (a nivel de ficheros, no con artisan)
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
      bootstrap/cache/config.php bootstrap/cache/events.php

# 2. Dependencias de produccion (regenera el descubrimiento ya sin paquetes dev)
composer install --no-dev --optimize-autoloader

# 3. Migraciones pendientes (idempotente)
php artisan migrate --force

# 4. Reconstruir caches. Imprescindible tras anadir rutas nuevas:
#    sin `route:cache` al dia, los endpoints recien creados responden 404.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Permisos y arranque
sudo chown -R www-data:www-data storage bootstrap/cache public/storage
php artisan up
sudo systemctl reload php8.3-fpm
```

Verificación:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<tu-dominio>/api/health   # 200
php artisan route:list | grep convert                                      # la ruta nueva existe
```

> **En Windows/XAMPP** abre PowerShell **como Administrador** y usa
> `backend/scripts/post-deploy.ps1`. El script apunta por defecto a
> `C:\xampp\htdocs\facturaPro\backend` y purga esas cachés antes de ejecutar
> artisan. Sin elevación, Windows deja esos archivos como solo lectura para el
> usuario interactivo aunque la cuenta sea `Administrator`. No apliques
> `systemctl`/`php8.3-fpm`: ahí basta con reiniciar Apache desde el panel de XAMPP.
