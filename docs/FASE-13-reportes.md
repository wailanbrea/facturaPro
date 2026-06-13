# FASE 13 - Reportes

Fecha: 2026-05-21

## Objetivo

Cerrar el modulo de reportes administrativos con filtros utiles, consultas consistentes y sin agregaciones monetarias incorrectas en escenarios multi-moneda.

## Problema tecnico corregido

El panel ya tenia un reporte basico, pero presentaba un defecto importante: sumaba montos de distintas monedas dentro de un mismo total. Eso hace que una cifra consolidada deje de ser valida contablemente.

La correccion no podia limitarse a los KPI principales. Tambien afectaba desgloses por fecha, estado y cliente.

## Implementacion

Se extrajo la logica de reportes a un servicio dedicado:

- `backend/app/Services/ReportService.php`

Ese servicio ahora:

- aplica filtros por `date_from`, `date_to` y `currency_code`
- excluye facturas anuladas
- calcula conteos generales y vencidas
- agrupa totales por moneda
- agrupa por fecha separando por moneda
- agrupa por estado separando por moneda
- agrupa por cliente separando por moneda
- lista facturas vencidas sin cargar relaciones innecesarias

La vista web fue actualizada en:

- `backend/resources/views/reports/index.blade.php`

Comportamiento nuevo:

- si el resultado filtrado pertenece a una sola moneda, se muestran KPI monetarios consolidados
- si el resultado contiene varias monedas, no se muestra una suma global engañosa
- en escenario multi-moneda, todos los importes se muestran agrupados por moneda

El controlador web tambien fue endurecido:

- `backend/app/Http/Controllers/Web/ReportController.php`

Se agrego validacion de filtros:

- `date_from`
- `date_to`
- `currency_code`

## Cobertura funcional cerrada

- Total facturado por fecha
- Total cobrado
- Total pendiente
- Facturas vencidas
- Facturas por estado
- Facturas por cliente
- Facturas por moneda
- Filtros por fecha y moneda

## Pruebas

Se ampliaron pruebas de panel web en:

- `backend/tests/Feature/Web/AdminPanelTest.php`

Casos cubiertos:

- render de reportes con multiples monedas
- confirmacion de desglose por moneda
- filtro por moneda y fecha
- visibilidad de facturas vencidas

## Validacion ejecutada

```text
php -l app/Services/ReportService.php
php -l app/Http/Controllers/Web/ReportController.php
php -l tests/Feature/Web/AdminPanelTest.php
php artisan test --filter=AdminPanelTest
php artisan test
```

Resultado final:

```text
Tests: 44 passed (187 assertions)
```

## Riesgos pendientes

- El reporte sigue siendo un resumen operativo web. No se implementaron exportaciones CSV/XLSX ni dashboards graficos.
- Si en produccion el volumen crece mucho, convendra evaluar indices adicionales o tablas/materializaciones para rangos amplios, pero para el estado actual no hay evidencia de necesidad.
