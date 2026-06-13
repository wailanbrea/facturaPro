# FASE 11 - Android base

Fecha: 2026-05-21

## Objetivo

Preparar la app Android para autenticarse contra la API Laravel, persistir token Sanctum y cargar la configuracion inicial desde `/api/settings/bootstrap`.

## Estado inicial

El proyecto Android ya existia en `android`, pero era una app Compose generada por Android Studio:

- Sin cliente API.
- Sin persistencia de sesion.
- Sin capas `data/domain/ui`.
- Sin navegacion real.
- Con pantalla demo `Hello Android`.

## Implementacion

Se implemento una base MVVM simple y mantenible:

- `FacturaProApplication` inicializa dependencias.
- `AppContainer` centraliza instancias compartidas sin Hilt todavia.
- `SessionStore` usa DataStore Preferences para guardar token y usuario.
- `FacturaProApi` define login, logout y bootstrap.
- `AuthInterceptor` agrega `Authorization: Bearer ...`.
- `AuthRepository` controla login/logout y persistencia.
- `SettingsRepository` carga catalogos iniciales.
- `LoginViewModel` modela estado de login, sesion y bootstrap.
- `LoginScreen` renderiza formulario de acceso.
- `HomeScreen` muestra resumen de catalogos cargados.
- `MainActivity` usa Navigation Compose para login/home.

## Estructura creada

```text
app/src/main/java/com/facturador/facturapro/
  data/
    local/
    remote/
    remote/dto/
    repository/
  di/
  domain/model/
  ui/auth/
  ui/home/
```

## Dependencias agregadas

- Navigation Compose.
- Lifecycle ViewModel Compose.
- Lifecycle Runtime Compose.
- DataStore Preferences.
- Retrofit.
- Gson Converter.
- OkHttp.
- OkHttp Logging Interceptor.

## Configuracion de API

`API_BASE_URL` queda configurado en:

```text
http://10.0.2.2:8001/api/
```

Motivo: en la maquina actual el backend Laravel responde en `127.0.0.1:8001`. El host `10.0.2.2` permite que el emulador Android acceda al localhost del equipo.

Si Laravel se levanta en otro puerto, actualizar:

```text
android/app/build.gradle.kts
```

## Seguridad

Se agrego `INTERNET` al manifest.

Tambien se habilito `android:usesCleartextTraffic="true"` para desarrollo local HTTP. Esto no debe quedar igual en produccion: FASE 15 debe mover la API a HTTPS y desactivar cleartext.

El token Sanctum se guarda en DataStore. Para produccion, si el riesgo lo amerita, se debe migrar a almacenamiento cifrado.

## Validacion ejecutada

Android:

```text
.\gradlew.bat :app:testDebugUnitTest
.\gradlew.bat :app:assembleDebug
.\gradlew.bat :app:lintDebug
.\gradlew.bat :app:testDebugUnitTest :app:assembleDebug
```

Resultado:

```text
BUILD SUCCESSFUL
lintDebug: 0 errores
```

Backend/API:

```text
GET  http://127.0.0.1:8001/api/health
POST http://127.0.0.1:8001/api/login
GET  http://127.0.0.1:8001/api/settings/bootstrap
```

Resultado observado:

```text
user: admin@facturapro.local
currencies: 3
taxes: 4
payment_terms: 3
warranties: 3
```

## Advertencias no bloqueantes

`lintDebug` reporta versiones mas nuevas disponibles de SDK/dependencias. No se actualizaron en esta fase porque el SDK 37 no esta instalado localmente y subir Retrofit/OkHttp/Kotlin sin necesidad funcional aumentaria riesgo de incompatibilidades. La app compila y pasa lint sin errores.

## Riesgos pendientes

- No se ejecuto prueba en emulador fisico desde Codex; se valido compilacion y contrato API desde host.
- `usesCleartextTraffic` solo es aceptable para desarrollo local.
- Falta pantalla funcional de clientes/facturas, que corresponde a FASE 12.
- Si el backend cambia de puerto, el login Android fallara hasta actualizar `API_BASE_URL`.
