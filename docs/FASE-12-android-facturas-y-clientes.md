# FASE 12 - Android facturas y clientes

Fecha: 2026-05-21

## Objetivo

Completar el flujo movil operativo para clientes y facturas sin duplicar reglas criticas del backend.

## Implementacion

Se reemplazo la pantalla Android de demostracion por un workspace autenticado con tres secciones:

- `Facturas`
- `Clientes`
- `Ajustes`

La app movil sigue un esquema simple y mantenible:

- Compose para UI.
- ViewModel + StateFlow para estado.
- Repositorios para acceso API.
- Laravel como fuente de verdad para calculo, estados, preview y PDF.

## Flujo de clientes

Se implemento:

- Listado de clientes con busqueda.
- Alta de cliente desde Android.
- Manejo de errores y estados de carga.

No se agrego edicion de clientes en esta fase porque el mayor valor operativo inmediato estaba en alta y seleccion para facturas. Puede incorporarse despues sin romper la arquitectura ya creada.

## Flujo de facturas

Se implemento:

- Listado de facturas.
- Creacion de borrador desde Android.
- Edicion de borrador.
- Detalle de factura.
- Emision de factura.
- Vista previa oficial HTML.
- Solicitud de generacion PDF.
- Descarga temporal y compartir PDF.

La creacion/edicion usa catálogos del bootstrap:

- clientes
- monedas
- impuestos
- terminos de pago
- garantias
- cuentas bancarias
- perfiles fiscales
- textos legales

La UI calcula un preview local de totales solo como referencia visual. El backend recalcula siempre el valor final al guardar.

## Preview oficial

Se agrego endpoint backend:

```text
GET /api/invoices/{invoice}/preview
```

Ese endpoint devuelve HTML usando la misma plantilla Blade oficial:

```text
backend/resources/views/pdf/invoice.blade.php
```

Android consume ese HTML autenticado y lo renderiza dentro de `WebView`. Esto evita recrear la factura en Compose y mantiene un unico contrato visual entre web, preview movil y PDF.

## PDF en Android

El PDF no se genera en el dispositivo.

Flujo:

1. Android solicita `POST /api/invoices/{invoice}/generate-pdf`.
2. Backend genera el archivo definitivo.
3. Android descarga `GET /api/invoices/{invoice}/download-pdf`.
4. El archivo se guarda en cache privada temporal.
5. Se comparte mediante `FileProvider`.

## Archivos principales

Android:

- `android/app/src/main/java/com/facturador/facturapro/ui/workspace/WorkspaceScreen.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/clients/ClientsScreen.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/invoices/InvoicesScreen.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/clients/ClientsViewModel.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/invoices/InvoicesViewModel.kt`
- `android/app/src/main/java/com/facturador/facturapro/data/repository/ClientRepository.kt`
- `android/app/src/main/java/com/facturador/facturapro/data/repository/InvoiceRepository.kt`
- `android/app/src/main/AndroidManifest.xml`
- `android/app/src/main/res/xml/file_paths.xml`

Backend:

- `backend/app/Http/Controllers/Api/InvoiceController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/Api/InvoiceApiTest.php`

## Validacion ejecutada

Backend:

```text
php artisan db:seed
php artisan test
```

Resultado:

```text
Tests: 43 passed (176 assertions)
```

Android:

```text
.\gradlew.bat :app:testDebugUnitTest
.\gradlew.bat :app:assembleDebug
.\gradlew.bat :app:lintDebug
```

Resultado:

```text
BUILD SUCCESSFUL
lintDebug sin errores
```

Contratos API validados localmente:

- login admin
- bootstrap
- create client
- create invoice
- preview HTML oficial

## Riesgos pendientes

- No se ejecuto navegacion en emulador desde Codex; la validacion fue por compilacion, lint y contratos API reales.
- La app depende de que el backend local siga expuesto en `127.0.0.1:8001`, reflejado en `10.0.2.2:8001` para emulador.
- `usesCleartextTraffic=true` sigue siendo solo para desarrollo local.
- Falta la siguiente fase de reportes moviles/administrativos si se desea expandir Android mas alla del flujo operativo actual.
