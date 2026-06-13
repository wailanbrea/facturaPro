# FASE 6 - API REST

## Objetivo

Exponer contratos API estables para web y Android, protegidos con Sanctum, con validacion de entrada y sin confiar en totales enviados por cliente.

## Archivos creados o modificados

### Rutas

- `backend/routes/api.php`

### Controladores

- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Api/ClientController.php`
- `backend/app/Http/Controllers/Api/InvoiceController.php`
- `backend/app/Http/Controllers/Api/SettingsController.php`

### Requests

- `backend/app/Http/Requests/Api/LoginRequest.php`
- `backend/app/Http/Requests/Api/StoreClientRequest.php`
- `backend/app/Http/Requests/Api/UpdateClientRequest.php`
- `backend/app/Http/Requests/Api/StoreInvoiceRequest.php`
- `backend/app/Http/Requests/Api/UpdateInvoiceRequest.php`
- `backend/app/Http/Requests/Api/MarkInvoicePaidRequest.php`

### Resources

- `backend/app/Http/Resources/Api/ClientResource.php`
- `backend/app/Http/Resources/Api/InvoiceResource.php`
- `backend/app/Http/Resources/Api/InvoiceItemResource.php`

### Tests

- `backend/tests/Feature/Api/AuthApiTest.php`
- `backend/tests/Feature/Api/ClientApiTest.php`
- `backend/tests/Feature/Api/InvoiceApiTest.php`
- `backend/tests/Feature/Api/SettingsApiTest.php`

## Endpoints publicos

```http
GET /api/health
POST /api/login
```

## Endpoints protegidos con Sanctum

### Auth

```http
POST /api/logout
GET /api/me
```

### Settings

```http
GET /api/settings/bootstrap
GET /api/currencies
GET /api/taxes
GET /api/payment-terms
GET /api/warranties
GET /api/bank-accounts
GET /api/fiscal-profiles
```

### Clientes

```http
GET /api/clients
POST /api/clients
GET /api/clients/{client}
PUT/PATCH /api/clients/{client}
DELETE /api/clients/{client}
```

Si un cliente tiene facturas, `DELETE` lo desactiva en vez de eliminarlo fisicamente.

### Facturas

```http
GET /api/invoices
POST /api/invoices
GET /api/invoices/{invoice}
PUT /api/invoices/{invoice}
DELETE /api/invoices/{invoice}
POST /api/invoices/{invoice}/issue
POST /api/invoices/{invoice}/cancel
POST /api/invoices/{invoice}/generate-pdf
GET /api/invoices/{invoice}/download-pdf
POST /api/invoices/{invoice}/mark-paid
```

## Reglas implementadas

- Login devuelve token Bearer de Sanctum.
- Rutas protegidas requieren `auth:sanctum`.
- Crear factura guarda snapshot de cliente, moneda y emisor.
- Crear y editar factura recalculan items y totales en backend.
- Totales enviados por cliente son ignorados.
- Emitir factura genera numero final si la factura no tiene numero.
- Numeracion usa `InvoiceNumberService`.
- Factura cancelada no se puede modificar ni pagar.
- Factura pagada no puede modificar campos monetarios.
- Solo facturas en borrador se pueden eliminar.
- `mark-paid` registra pago y recalcula estado.
- `generate-pdf` responde `501` hasta FASE 9.

## Validacion ejecutada

```bash
php artisan route:list --path=api
php artisan test
```

Resultado:

```text
Tests: 29 passed (103 assertions)
```

Tambien se valido contra servidor local:

```http
POST http://127.0.0.1:8000/api/login
GET  http://127.0.0.1:8000/api/me
GET  http://127.0.0.1:8000/api/currencies
```

Resultado validado:

```json
{"user":"admin@facturapro.local","currencies":3}
```

## Riesgos pendientes

- Permisos por rol aun no estan aplicados a rutas. Sanctum protege autenticacion, pero autorizacion granular corresponde a FASE 10.
- PDF real no se genera todavia. `InvoicePdfService::generate()` se implementara en FASE 9.
- Aun no existe panel web para consumir la API desde interfaz administrativa. Eso corresponde a FASE 7.

## Resultado

FASE 6 completada. La siguiente fase es crear el panel web administrativo.
