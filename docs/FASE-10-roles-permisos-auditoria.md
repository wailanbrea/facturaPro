# FASE 10 - Roles, permisos y auditoria

Fecha: 2026-05-21

## Objetivo

Aplicar autorizacion granular a operaciones criticas web/API y registrar auditoria suficiente para acciones sensibles sobre facturas.

## Implementacion

Se agrego middleware propio `permission:*` registrado en `bootstrap/app.php`. El middleware valida al usuario autenticado y comprueba permisos activos asociados a roles activos.

La autorizacion queda declarada en rutas, no dispersa dentro de controladores. Esta decision reduce acoplamiento, facilita revisar el mapa de permisos y evita que cada accion repita validaciones manuales.

## Roles y permisos

Roles semilla:

- `admin`: acceso total.
- `vendedor`: clientes, facturas, pagos, PDF y reportes.
- `tecnico`: consulta y descarga de facturas.
- `lectura`: consulta de facturas y reportes.

Permisos semilla:

- `crear_factura`
- `editar_factura`
- `emitir_factura`
- `anular_factura`
- `ver_factura`
- `descargar_pdf`
- `registrar_pagos`
- `gestionar_clientes`
- `configurar_sistema`
- `gestionar_usuarios`
- `ver_reportes`

## Proteccion aplicada

API:

- Clientes: `gestionar_clientes`.
- Listar/ver facturas: `ver_factura`.
- Crear facturas: `crear_factura`.
- Editar/eliminar borradores: `editar_factura`.
- Emitir: `emitir_factura`.
- Anular: `anular_factura`.
- Generar/descargar PDF: `descargar_pdf`.
- Registrar pagos: `registrar_pagos`.

Web:

- Clientes: `gestionar_clientes`.
- Facturas: permisos equivalentes a API.
- Configuracion: `configurar_sistema`.
- Usuarios/roles: `gestionar_usuarios`.
- Reportes: `ver_reportes`.

Los endpoints API de bootstrap/catalogos quedan autenticados sin permiso granular adicional porque Android necesitara cargar configuracion base despues del login. Endurecer esos endpoints puede evaluarse cuando se definan permisos moviles especificos.

## Auditoria

Se registran acciones sobre facturas:

- `invoice.created`
- `invoice.updated`
- `invoice.issued`
- `invoice.cancelled`
- `invoice.payment_recorded`
- `invoice.pdf_generated`

Cada registro incluye, cuando existe:

- Usuario autenticado (`user_id`).
- Sujeto auditado (`subject_type`, `subject_id`).
- Propiedades relevantes.
- IP.
- User-Agent.

## Reglas reforzadas

- Usuario autenticado sin permiso recibe `403`.
- Factura anulada no puede modificarse ni recibir pagos.
- Factura pagada no permite cambios de campos monetarios en API.
- Panel web solo permite editar facturas en borrador.
- No se permite registrar pagos cuando la factura ya no tiene saldo pendiente.

## Archivos modificados

- `backend/app/Models/User.php`
- `backend/app/Http/Middleware/EnsureUserHasPermission.php`
- `backend/bootstrap/app.php`
- `backend/database/seeders/DatabaseSeeder.php`
- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/app/Http/Controllers/Api/InvoiceController.php`
- `backend/app/Http/Controllers/Web/InvoiceController.php`
- `backend/tests/Feature/Api/ClientApiTest.php`
- `backend/tests/Feature/Api/InvoiceApiTest.php`
- `backend/tests/Feature/Web/AdminPanelTest.php`

## Validacion ejecutada

- `php -l app\Http\Controllers\Api\InvoiceController.php`
- `php -l app\Http\Controllers\Web\InvoiceController.php`
- `php -l app\Http\Middleware\EnsureUserHasPermission.php`
- `php -l app\Models\User.php`
- `php artisan route:list --path=invoices -v`
- `php artisan test`

Resultado:

```text
Tests: 42 passed (171 assertions)
```

## Riesgos pendientes

- Los permisos de catalogos API siguen agrupados bajo autenticacion general. Si Android requiere usuarios con lectura limitada de catalogos, conviene crear permisos especificos de catalogo en una fase posterior.
- `User::hasAnyPermission()` consulta permisos por permiso recibido. El volumen actual es bajo; si crece el numero de permisos por request, conviene cachear permisos por usuario durante la peticion.
- No se implemento pantalla de consulta de auditoria. La informacion ya queda persistida, pero una vista de administracion deberia agregarse antes de produccion si se requiere trazabilidad operativa visible.
