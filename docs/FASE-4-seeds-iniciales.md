# FASE 4 - Seeds iniciales

## Objetivo

Crear datos minimos para operar el sistema localmente sin tocar codigo: usuario admin, roles, permisos, monedas, impuestos, terminos, garantias, cuenta bancaria, perfil fiscal, textos legales, numeracion y settings base.

## Archivo modificado

```text
backend/database/seeders/DatabaseSeeder.php
```

## Datos creados

### Usuario admin local

```text
Email: admin@facturapro.local
Password temporal: FacturaPro123!
```

El password es solo para desarrollo local. Debe cambiarse antes de cualquier entorno real.

### Roles

- `ADMIN`
- `VENDEDOR`
- `TECNICO`
- `LECTURA`

### Permisos

- `crear_factura`
- `editar_factura`
- `emitir_factura`
- `anular_factura`
- `ver_factura`
- `descargar_pdf`
- `configurar_sistema`
- `gestionar_usuarios`
- `ver_reportes`

### Monedas

- EUR / Euro
- USD / Dollar
- DOP / Peso Dominicano

Moneda predeterminada: `DOP`.

### Impuestos

- IVA 21%
- ITBIS 18%
- Tax 7%
- Exento 0%

Impuesto predeterminado: `ITBIS 18%`.

### Terminos de pago

- AL CONTADO
- CREDITO 15 DIAS
- CREDITO 30 DIAS

Termino predeterminado: `AL CONTADO`.

### Garantias

- GARANTIA DE 6 MESES EN PIEZAS Y SERVICIOS DEL FABRICANTE
- GARANTIA DE 1 ANO EN PIEZAS Y SERVICIOS DEL FABRICANTE
- GARANTIA DE 3 ANOS EN PIEZAS Y SERVICIOS DEL FABRICANTE

### Otros datos

- Perfil fiscal demo profesional.
- Cuenta bancaria demo con etiqueta `Principal`.
- Texto legal predeterminado.
- Configuracion de numeracion `FAC-000001`.
- Settings globales de impuestos y defaults de factura.

## Validacion ejecutada

```bash
php -l database/seeders/DatabaseSeeder.php
php artisan db:seed
php artisan db:seed
php artisan tinker --execute="..."
php artisan test
```

Resultado validado:

```json
{
  "users": 1,
  "roles": 4,
  "permissions": 9,
  "currencies": 3,
  "taxes": 4,
  "payment_terms": 3,
  "warranties": 3,
  "bank_accounts": 1,
  "fiscal_profiles": 1,
  "legal_texts": 1,
  "invoice_number_settings": 1,
  "settings": 4,
  "admin_roles": 1
}
```

El seeder es idempotente: puede ejecutarse mas de una vez sin duplicar datos.

## Riesgos pendientes

- No existe pantalla ni endpoint de login todavia.
- No existe politica de permisos aplicada en rutas/controladores todavia.
- El perfil fiscal y cuenta bancaria son datos demo; deben reemplazarse por datos reales antes de emitir facturas reales.

## Resultado

FASE 4 completada. La siguiente fase es implementar servicios de dominio.
