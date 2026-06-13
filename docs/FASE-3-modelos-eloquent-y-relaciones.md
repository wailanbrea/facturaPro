# FASE 3 - Modelos Eloquent y relaciones

## Objetivo

Crear modelos Eloquent limpios para las tablas de dominio, con asignacion masiva controlada, casts correctos y relaciones necesarias para servicios, API y panel web.

## Modelos creados

- `ActivityLog`
- `BankAccount`
- `Client`
- `Currency`
- `FiscalProfile`
- `Invoice`
- `InvoiceItem`
- `InvoiceNumberSetting`
- `InvoicePayment`
- `LegalText`
- `PaymentTerm`
- `Permission`
- `Role`
- `Setting`
- `Tax`
- `Warranty`

Tambien se actualizo `User`.

## Decisiones tecnicas

### Fillable explicito

Se uso `fillable` explicito en modelos de dominio. Es mas verboso, pero evita abrir asignacion masiva accidental cuando los controladores y requests empiecen a crecer.

### Casts

Se agregaron casts para:

- Fechas: `date`.
- Fecha/hora: `datetime`.
- Dinero/cantidades/tasas: `decimal:4`.
- Banderas: `boolean`.
- Configuracion JSON: `array`.
- Enteros de control: `integer`.

### Logica de negocio

No se agrego logica de calculo, numeracion ni cambio de estados en modelos. Eso corresponde a servicios de dominio en fases posteriores.

## Relaciones principales

### Invoice

- `paymentTerm()`
- `client()`
- `currency()`
- `fiscalProfile()`
- `bankAccount()`
- `warranty()`
- `items()`
- `payments()`
- `createdBy()`
- `updatedBy()`

### InvoiceItem

- `invoice()`
- `tax()`

### InvoicePayment

- `invoice()`
- `createdBy()`

### Configuracion

- `Currency -> bankAccounts()`
- `Currency -> invoices()`
- `Tax -> invoiceItems()`
- `PaymentTerm -> invoices()`
- `Warranty -> invoices()`
- `FiscalProfile -> invoices()`
- `BankAccount -> currency()`
- `BankAccount -> invoices()`

### Autorizacion

- `User -> roles()`
- `Role -> users()`
- `Role -> permissions()`
- `Permission -> roles()`

### Auditoria

- `ActivityLog -> user()`
- `ActivityLog -> subject()`

## Validacion ejecutada

```bash
Get-ChildItem app\Models -Filter *.php | ForEach-Object { php -l $_.FullName }
php artisan test
php artisan tinker --execute="..."
```

Resultado:

- Todos los modelos pasan `php -l`.
- `php artisan test`: 2 tests pasados.
- Se creo una factura temporal dentro de una transaccion y se verifico:
  - `Invoice -> client`
  - `Invoice -> currency`
  - `Invoice -> paymentTerm`
  - `Invoice -> items -> tax`
- La transaccion se revirtio con rollback para no dejar datos de prueba.

## Riesgos pendientes

- Aun no hay seeds iniciales. Sin seeds, la app no tiene monedas, impuestos, roles ni usuario admin operativo.
- Aun no hay factories de dominio ni pruebas especificas de relaciones. Conviene agregarlas cuando se implementen servicios y API.
- La asignacion de multiples defaults sigue pendiente de validacion por servicio/request.

## Resultado

FASE 3 completada. La siguiente fase es crear seeds iniciales.
