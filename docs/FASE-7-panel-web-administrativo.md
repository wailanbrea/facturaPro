# FASE 7 - Panel web administrativo

Fecha: 2026-05-21

## Alcance implementado

Se implemento el panel web administrativo usando sesiones web de Laravel, Blade y controladores server-side.

La vista previa oficial y la generacion/descarga de PDF no se cierran en esta fase porque pertenecen a FASE 8 y FASE 9. Esa separacion evita duplicar plantillas y mantiene un unico contrato HTML/CSS A4 para navegador y PDF.

## Decision tecnica

Se uso Blade server-side sin Livewire en esta iteracion.

Motivo: el flujo actual no necesita estado reactivo complejo; el calculo final y las transiciones criticas viven en servicios de dominio backend. Agregar Livewire ahora incrementaria superficie de mantenimiento sin resolver una necesidad real. Si en la configuracion avanzada aparecen formularios dinamicos complejos, Livewire puede incorporarse de forma puntual.

## Rutas web implementadas

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /`
- CRUD web de clientes excepto `show`
- `GET /invoices`
- `GET /invoices/create`
- `POST /invoices`
- `GET /invoices/{invoice}/edit`
- `PUT /invoices/{invoice}`
- `GET /invoices/{invoice}`
- `POST /invoices/{invoice}/issue`
- `POST /invoices/{invoice}/cancel`
- `POST /invoices/{invoice}/mark-paid`
- `GET /settings`
- CRUD de catalogos bajo `/settings/{catalog}`
- CRUD basico de usuarios bajo `/users`
- `GET /reports`

Las rutas web usan prefijo de nombre `web.*` para evitar colisiones con nombres de rutas API.

## Pantallas implementadas

- Login.
- Dashboard con metricas principales.
- Listado de facturas.
- Crear factura.
- Editar factura en borrador.
- Detalle de factura.
- Clientes: listado, alta, edicion y eliminacion/desactivacion segura.
- Configuracion general: monedas, impuestos, terminos, garantias, cuentas bancarias, perfiles fiscales, textos legales y numeracion.
- Usuarios y roles.
- Reportes basicos.

## Flujo de factura implementado

1. El usuario autenticado crea una factura en borrador desde el panel.
2. El backend recalcula totales con `InvoiceCalculationService`.
3. Se guardan snapshots de cliente, moneda, perfil fiscal, cuenta bancaria, garantia e items.
4. El usuario puede emitir la factura.
5. La numeracion se genera con `InvoiceNumberService`.
6. El usuario puede marcar la factura como pagada.

## Archivos principales

- `backend/routes/web.php`
- `backend/app/Http/Controllers/Web/AuthController.php`
- `backend/app/Http/Controllers/Web/DashboardController.php`
- `backend/app/Http/Controllers/Web/ClientController.php`
- `backend/app/Http/Controllers/Web/InvoiceController.php`
- `backend/app/Http/Controllers/Web/SettingsController.php`
- `backend/app/Http/Controllers/Web/SettingsCatalogController.php`
- `backend/app/Http/Controllers/Web/UserController.php`
- `backend/app/Http/Controllers/Web/ReportController.php`
- `backend/resources/views/layouts/app.blade.php`
- `backend/resources/views/auth/login.blade.php`
- `backend/resources/views/dashboard/index.blade.php`
- `backend/resources/views/clients/index.blade.php`
- `backend/resources/views/clients/form.blade.php`
- `backend/resources/views/invoices/index.blade.php`
- `backend/resources/views/invoices/create.blade.php`
- `backend/resources/views/invoices/show.blade.php`
- `backend/resources/views/settings/index.blade.php`
- `backend/resources/views/settings/catalog-index.blade.php`
- `backend/resources/views/settings/catalog-form.blade.php`
- `backend/resources/views/users/index.blade.php`
- `backend/resources/views/users/form.blade.php`
- `backend/resources/views/reports/index.blade.php`
- `backend/tests/Feature/Web/AdminPanelTest.php`

## Validacion ejecutada

- `php -l` en controladores web modificados.
- `php artisan route:list --path=clients`.
- `php artisan test`.
- Navegacion real en navegador local contra `http://127.0.0.1:8000`:
  - Login.
  - Dashboard.
  - Listado de facturas.
  - Configuracion.
  - Sin errores de consola detectados.
- Validacion HTTP autenticada contra servidor limpio `http://127.0.0.1:8001`:
  - `/settings`
  - `/settings/taxes`
  - `/users`
  - `/reports`

Resultado de pruebas automatizadas:

```text
Tests: 39 passed (143 assertions)
```

## Fuera de alcance de FASE 7

- Vista previa oficial de factura basada en la plantilla A4.
- Generacion y descarga PDF desde web. Depende de FASE 8 y FASE 9.
- Autorizacion granular por permisos en rutas web. Depende de FASE 10.

## Riesgos tecnicos

- La configuracion esta visible en web, pero todavia no es administrable desde interfaz. No debe considerarse lista para operacion no tecnica.
- El formulario de factura es funcional, pero debe evolucionar para editar borradores sin romper reglas de estado.
- La vista de detalle no sustituye la vista previa oficial de factura; esa debe nacer de la plantilla HTML/CSS de FASE 8.
