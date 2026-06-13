# Android - Reportes moviles

Fecha: 2026-05-21

## Objetivo

Completar la app Android con reportes operativos usando el backend como fuente unica de verdad.

## Decision tecnica

Android no calcula reportes localmente. La app consume `GET /api/reports`, que reutiliza `ReportService` en Laravel. Esto evita divergencias con el panel web y mantiene las reglas de agregacion monetaria en backend.

## Backend

Se agrego:

- `backend/app/Http/Controllers/Api/ReportController.php`
- ruta `GET /api/reports`
- proteccion con permiso `ver_reportes`
- pruebas en `backend/tests/Feature/Api/ReportApiTest.php`

El contrato devuelve:

- filtros aplicados
- resumen de facturas y vencidas
- totales por moneda
- totales por fecha
- totales por estado
- totales por cliente
- facturas vencidas
- bandera `can_show_unified_money_totals`

Los importes se normalizan como strings decimales de 4 posiciones, consistente con el resto de la API.

## Android

Se agrego:

- `android/app/src/main/java/com/facturador/facturapro/domain/model/ReportModels.kt`
- `android/app/src/main/java/com/facturador/facturapro/data/remote/dto/ReportDtos.kt`
- `android/app/src/main/java/com/facturador/facturapro/data/repository/ReportRepository.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/reports/ReportsUiState.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/reports/ReportsViewModel.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/reports/ReportsScreen.kt`

La pantalla se integro como pestaña `Reportes` dentro del workspace Android.

## Funcionalidad

- filtros por fecha con `DatePicker` Compose, enviando `YYYY-MM-DD` al backend
- filtro por moneda
- resumen de facturas y vencidas
- totales consolidados solo si el resultado pertenece a una moneda
- totales por moneda cuando existen varias divisas
- vistas resumidas por fecha, estado, cliente y vencidas

## Validacion ejecutada

Backend:

```text
php artisan test
```

Resultado:

```text
Tests: 60 passed (260 assertions)
```

Android:

```text
.\gradlew.bat :app:testDebugUnitTest :app:assembleDebug :app:lintDebug
```

Resultado:

```text
BUILD SUCCESSFUL
```

## Riesgos pendientes

- No se ejecuto prueba manual en emulador/dispositivo desde Codex.
- La pantalla es de resumen operativo, no de analitica avanzada ni exportacion.
