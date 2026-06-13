# FASE 14 - Pruebas integrales

Fecha: 2026-05-21

## Objetivo

Elevar la cobertura funcional del sistema completo, cerrando casos criticos de calculo, estados de factura, PDF y flujos Android sin depender de validacion manual fragil.

## Analisis tecnico

La cobertura previa ya validaba el flujo base, pero FASE 14 exigia algo mas preciso:

- matriz de monedas
- matriz de impuestos
- estados criticos de factura
- confirmacion de generacion y descarga PDF
- validacion de flujos Android de login y creacion de factura

El riesgo principal estaba en dejar Android sin pruebas locales reales y en asumir que la cobertura previa de API ya implicaba todos los estados exigidos. No era asi.

## Implementacion

### Backend

Se ampliaron pruebas en:

- `backend/tests/Unit/Services/InvoiceCalculationServiceTest.php`
- `backend/tests/Feature/Api/InvoiceApiTest.php`

Cobertura agregada:

- impuestos `21%`, `18%`, `7%` y `0%`
- monedas `EUR`, `USD` y `DOP`
- pago parcial
- factura pagada
- factura vencida
- anulacion
- PDF generado
- PDF descargado

Se reemplazaron `@dataProvider` por atributos `#[DataProvider(...)]` para evitar deuda con PHPUnit 12.

### Android

Se introdujeron contratos minimos para mejorar testabilidad:

- `AuthRepositoryContract`
- `SettingsRepositoryContract`
- `InvoiceRepositoryContract`
- `SessionStoreContract`

Archivos principales:

- `android/app/src/main/java/com/facturador/facturapro/data/repository/RepositoryContracts.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/auth/LoginViewModel.kt`
- `android/app/src/main/java/com/facturador/facturapro/ui/invoices/InvoicesViewModel.kt`

Con ese desacople se agregaron pruebas unitarias locales:

- `android/app/src/test/java/com/facturador/facturapro/ui/auth/LoginViewModelTest.kt`
- `android/app/src/test/java/com/facturador/facturapro/ui/invoices/InvoicesViewModelTest.kt`
- `android/app/src/test/java/com/facturador/facturapro/testutil/MainDispatcherRule.kt`

Cobertura Android agregada:

- login exitoso con carga de bootstrap
- validacion de credenciales vacias
- creacion de factura con actualizacion correcta del estado UI

## Validacion ejecutada

Backend:

```text
php artisan test
```

Resultado:

```text
Tests: 58 passed (246 assertions)
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
```

## Riesgos pendientes

- Las pruebas Android agregadas son unitarias locales; no sustituyen una corrida instrumentada completa en emulador o dispositivo real.
- La validacion visual del PDF sigue dependiendo de Chrome/Chromium disponible en entorno local, aunque el contenido binario y la descarga quedaron cubiertos.
- FASE 15 sigue pendiente para endurecimiento de despliegue y validacion en entorno publico.
