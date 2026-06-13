# FASE 5 - Servicios de dominio

## Objetivo

Centralizar reglas criticas en servicios testeables, manteniendo modelos, controladores y vistas sin logica pesada.

## Servicios creados

### `InvoiceCalculationService`

Responsable de calcular:

- Subtotal por linea.
- Impuesto por linea.
- Precio unitario.
- Total por linea.
- Subtotal general.
- Impuesto total.
- Total.
- Importe recibido.
- Balance pendiente.

Soporta:

- Precios sin impuesto incluido.
- Precios con impuesto incluido.
- Lineas exentas.
- Impuestos diferentes por linea.

Usa `brick/math` para evitar errores de precision binaria de `float`.

### `InvoiceNumberService`

Responsable de:

- Generar numero de factura.
- Aplicar prefijo, serie y longitud con ceros.
- Reinicio anual o mensual.
- Bloqueo transaccional con `lockForUpdate`.
- Saltar numeros ya existentes si detecta duplicado.

### `InvoiceStatusService`

Responsable de determinar estado:

- `draft`
- `issued`
- `paid`
- `partially_paid`
- `cancelled`
- `overdue`

Conserva `draft` y `cancelled` como estados manuales protegidos.

### `CurrencyFormatterService`

Formatea importes usando snapshot/configuracion de moneda:

- Simbolo.
- Separador decimal.
- Separador de miles.
- Cantidad de decimales.
- Posicion del simbolo.

### `SettingsBootstrapService`

Devuelve configuracion activa para clientes web y Android:

- Monedas.
- Impuestos.
- Terminos.
- Garantias.
- Cuentas bancarias.
- Perfiles fiscales.
- Textos legales.
- Numeracion.
- Settings agrupados.

### `ActivityLogService`

Registra acciones auditables con:

- Usuario.
- Accion.
- Sujeto.
- Propiedades JSON.
- IP.
- User agent.

### `InvoicePdfService`

Define contrato inicial y ruta segura del PDF:

```text
storage/app/public/invoices/{invoice_number}.pdf
```

La generacion real del PDF no se implementa en esta fase. Queda para FASE 9, donde se integrara plantilla Blade A4 y Browsershot/Puppeteer.

## Cambio de entorno de pruebas

`phpunit.xml` fue configurado para usar SQLite en memoria:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Razon: los tests deben ser aislados y no destruir la base MySQL local. MySQL ya fue validado con `migrate:fresh` en FASE 2.

## Pruebas agregadas

- `InvoiceCalculationServiceTest`
- `InvoiceStatusServiceTest`
- `CurrencyFormatterServiceTest`
- `InvoiceNumberServiceTest`
- `SettingsBootstrapServiceTest`
- `ActivityLogServiceTest`

## Validacion ejecutada

```bash
php artisan test
Get-ChildItem app\Services -Filter *.php | ForEach-Object { php -l $_.FullName }
php artisan migrate:status
```

Resultado:

```text
Tests: 18 passed (41 assertions)
```

Tambien se valido:

```http
GET /api/health
```

Respuesta:

```json
{"status":"ok","app":"FacturaPro","environment":"local"}
```

## Error detectado y corregido

Las pruebas detectaron que `InvoiceCalculationService` convertia `total` a string antes de calcular `balance_due`. Se corrigio manteniendo `BigDecimal` hasta completar todos los calculos.

## Riesgos pendientes

- `InvoicePdfService::generate()` aun lanza excepcion intencional porque la generacion real pertenece a FASE 9.
- Los servicios aun no estan conectados a controladores/API. Eso corresponde a FASE 6.
- Las reglas de permisos todavia no estan aplicadas a endpoints. Eso corresponde a fases posteriores.

## Resultado

FASE 5 completada. La siguiente fase es crear la API REST.
