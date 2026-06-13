# FASE 2 - Base de datos y migraciones

## Objetivo

Crear la estructura persistente principal de FacturaPro con tipos monetarios seguros, llaves foraneas, indices utiles y tablas configurables.

## Migracion creada

```text
backend/database/migrations/2026_05_21_030000_create_facturapro_domain_tables.php
```

## Tablas creadas

- `roles`
- `permissions`
- `permission_role`
- `role_user`
- `clients`
- `currencies`
- `taxes`
- `payment_terms`
- `warranties`
- `fiscal_profiles`
- `bank_accounts`
- `legal_texts`
- `invoice_number_settings`
- `settings`
- `invoices`
- `invoice_items`
- `invoice_payments`
- `activity_logs`

La tabla `users` ya existia desde la base Laravel. Sanctum tambien creo `personal_access_tokens`.

## Decisiones tecnicas

### Dinero y cantidades

Se uso `decimal(15,4)` para importes y cantidades:

- Evita errores binarios de `float`.
- Permite decimales en cantidades.
- Da margen para facturas grandes.
- Mantiene precision suficiente antes de aplicar formato por moneda.

### Impuestos

Se uso `decimal(7,4)` para tasas de impuesto. La convencion sera almacenar porcentaje humano, por ejemplo:

```text
18.0000 = 18%
21.0000 = 21%
0.0000 = exento
```

El servicio de calculo debera dividir entre 100 para operaciones.

### Snapshots historicos

`invoices` guarda copia de:

- Cliente.
- Moneda.
- Configuracion de formato de moneda.
- Datos de vendedor/emisor.
- Garantia.
- Texto legal.

Esto evita que una factura emitida cambie si luego se edita la configuracion global.

### Autorizacion

Se agregaron pivotes:

- `role_user`
- `permission_role`

Aunque no estaban en la lista inicial, son necesarios para asignar roles y permisos de forma normalizada.

## Indices relevantes

- `invoices.invoice_number` unico.
- `invoices.invoice_date`.
- `invoices.due_date`.
- `invoices.status`.
- `invoices.client_id + invoice_date`.
- `invoices.status + due_date`.
- `invoices.currency_code + invoice_date`.
- `clients.name`.
- `clients.tax_id`.
- `clients.email`.
- `currencies.code` unico.
- `settings.key` unico.
- `roles.slug` unico.
- `permissions.slug` unico.

## Llaves foraneas relevantes

- `invoices.client_id -> clients.id` con `restrict`.
- `invoices.currency_id -> currencies.id` con `restrict`.
- `invoices.payment_term_id -> payment_terms.id` con `set null`.
- `invoices.fiscal_profile_id -> fiscal_profiles.id` con `set null`.
- `invoices.bank_account_id -> bank_accounts.id` con `set null`.
- `invoices.warranty_id -> warranties.id` con `set null`.
- `invoice_items.invoice_id -> invoices.id` con `cascade`.
- `invoice_items.tax_id -> taxes.id` con `set null`.
- `invoice_payments.invoice_id -> invoices.id` con `cascade`.

La regla es conservar facturas y snapshots historicos aunque una configuracion opcional sea desactivada o eliminada.

## Validacion ejecutada

```bash
php artisan migrate
php artisan migrate:fresh
php artisan test
php artisan db:table invoices
php artisan db:table invoice_items
php artisan db:table invoice_payments
php artisan db:table currencies
```

Resultado:

- Migraciones corren desde cero sin error.
- `php artisan test`: 2 tests pasados.
- `/api/health` sigue respondiendo correctamente.
- Tablas inspeccionadas muestran `decimal`, indices y llaves foraneas esperadas.

## Error de entorno corregido

Durante la inspeccion con `php artisan db:table`, Laravel fallo porque `intl` no estaba activo.

Se corrigio en:

```text
C:\xampp\php\php.ini
```

Cambio aplicado:

```ini
extension=intl
```

Validado con:

```bash
php -m
```

## Riesgos pendientes

- Aun no hay modelos Eloquent para estas tablas. Eso corresponde a FASE 3.
- Aun no hay seeds iniciales. Eso corresponde a FASE 4.
- La politica de multiples defaults por tabla se controlara en servicios/validaciones, no solo con constraint de base, porque MySQL no ofrece indices parciales simples portables para este caso.

## Resultado

FASE 2 completada. La siguiente fase es crear modelos Eloquent, casts y relaciones.
