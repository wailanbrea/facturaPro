# TODO.md - FacturaPro

## Estado General

- Proyecto: sistema web y movil personalizado de facturacion.
- Backend: Laravel 12, MySQL, API REST con Laravel Sanctum.
- Web: Laravel Blade + Livewire, salvo justificacion tecnica posterior.
- Android: Kotlin, Jetpack Compose, MVVM, Repository Pattern, Retrofit, OkHttp, Coroutines, StateFlow, Navigation Compose y DataStore.
- PDF: plantilla HTML/CSS unica renderizada por backend con Browsershot/Puppeteer.
- Plantilla base: `Factura P L - Oficial.xlsx`.
- Estado actual: FASE 15 iniciada. Infraestructura de despliegue preparada; pendiente validacion en VPS y API publica.
- Fecha de inicio documentada: 2026-05-20.

## Reglas Operativas

- Antes de iniciar cualquier fase se debe leer este archivo.
- No se debe saltar una fase sin registrar motivo tecnico.
- No se deben eliminar tareas. Si dejan de aplicar, marcarlas como `[NO APLICA]` con explicacion.
- Todo error encontrado debe registrarse en la seccion `Registro de errores`.
- El backend es la fuente de verdad para totales, numeracion, estados y reglas de edicion.
- Android puede calcular previews locales, pero nunca debe generar PDF ni imponer totales finales.
- La plantilla `resources/views/pdf/invoice.blade.php` sera unica para vista previa web y PDF final.
- Los datos configurables no deben quedar quemados en codigo: monedas, impuestos, garantias, cuentas, textos legales, emisor y numeracion.
- Las facturas deben guardar snapshot de moneda, cliente, emisor, impuesto, garantia y textos legales necesarios para preservar historico.
- Toda operacion critica debe tener validacion de request, autorizacion, manejo de errores y pruebas.

## Decisiones Tecnicas Base

- Usar Laravel 12 porque el proyecto arranca desde cero y permite una base actual, mantenible y compatible con PHP moderno.
- Usar Blade + Livewire para el panel web inicial: reduce complejidad operativa frente a SPA completa y encaja con formularios administrativos densos.
- Usar Sanctum para la API Android y sesiones web normales para el panel.
- Usar servicios de dominio dedicados para calculo, numeracion, estados y PDF. No mezclar esta logica en controladores, modelos ni vistas.
- Usar transacciones y bloqueo de fila para generar numeros de factura y evitar duplicados bajo concurrencia.
- Usar decimal en base de datos para importes. No usar float para dinero.
- Usar una vista HTML/CSS A4 como contrato visual de factura, testeada en navegador y despues con PDF.

## FASE 0 - Analisis de plantilla y arquitectura

### Objetivo

Entender la plantilla Excel, pantallas de referencia, campos, distribucion, formulas y reglas antes de crear codigo productivo.

### Tareas

- [x] Revisar `Factura P L - Oficial.xlsx`.
- [x] Identificar hojas del archivo.
- [x] Identificar campos visibles de la factura.
- [x] Identificar campos calculados.
- [x] Identificar formulas existentes.
- [x] Identificar textos legales.
- [x] Identificar garantias disponibles.
- [x] Identificar terminos de pago.
- [x] Identificar cuentas bancarias.
- [x] Identificar datos fiscales/emisores.
- [x] Documentar estructura visual de la factura.
- [x] Definir campos editables.
- [x] Definir campos configurables.
- [x] Definir campos calculados.
- [x] Confirmar tamano objetivo PDF: A4 vertical.
- [x] Confirmar que vista previa web y PDF deben usar la misma plantilla.
- [x] Documentar riesgos tecnicos iniciales.

### Entregables

- [x] `docs/FASE-0-analisis-plantilla-y-arquitectura.md`.

### Criterios de aceptacion

- [x] Existe documento tecnico con todos los campos relevantes.
- [x] Existe mapa de secciones de la factura.
- [x] Existe lista de campos editables, configurables y calculados.
- [x] Existe decision tecnica para generacion PDF.

## FASE 1 - Arquitectura base Laravel

### Objetivo

Crear una base Laravel 12 limpia, ejecutable y lista para evolucionar por modulos.

### Tareas

- [x] Verificar version de PHP compatible con Laravel 12.
- [x] Verificar Composer.
- [x] Crear proyecto Laravel 12 en el directorio del backend.
- [x] Definir si Laravel vivira en raiz o subcarpeta `backend`.
- [x] Configurar `.env`.
- [x] Configurar MySQL.
- [x] Configurar zona horaria `America/Santo_Domingo`.
- [x] Configurar locale base.
- [x] Instalar y configurar Laravel Sanctum.
- [x] Crear ruta `/api/health`.
- [x] Crear estructura `app/Services`.
- [x] Crear estructura `app/DTOs`.
- [x] Crear estructura de Form Requests.
- [x] Crear estructura de API Resources.
- [x] Configurar almacenamiento publico.
- [x] Ejecutar prueba de arranque.

### Criterios de aceptacion

- [x] Laravel ejecuta sin errores.
- [x] `/api/health` responde `ok`.
- [x] La conexion MySQL funciona.
- [x] Sanctum queda instalado y configurado.
- [x] Se documenta comando exacto para levantar el backend.

### Notas de validacion

- PHP: `8.2.12`.
- Composer: `2.8.11`.
- Laravel: `12.60.2`.
- Node: `24.4.0`.
- npm: `11.4.2`.
- Backend ubicado en `backend`.
- Base MySQL creada: `facturapro`.
- Servidor local iniciado en `http://127.0.0.1:8000`.
- Validacion HTTP: `GET /api/health` devuelve `{"status":"ok","app":"FacturaPro","environment":"local"}`.
- Pruebas ejecutadas: `php artisan test`, 2 tests pasados.

## FASE 2 - Base de datos y migraciones

### Objetivo

Crear el modelo persistente principal con indices, llaves foraneas y tipos monetarios correctos.

### Migraciones

- [x] `users`.
- [x] `clients`.
- [x] `currencies`.
- [x] `taxes`.
- [x] `payment_terms`.
- [x] `warranties`.
- [x] `bank_accounts`.
- [x] `fiscal_profiles`.
- [x] `legal_texts`.
- [x] `invoice_number_settings`.
- [x] `invoices`.
- [x] `invoice_items`.
- [x] `invoice_payments`.
- [x] `roles`.
- [x] `permissions`.
- [x] `activity_logs`.
- [x] `settings`.
- [x] `role_user` agregado por necesidad tecnica para asignar roles.
- [x] `permission_role` agregado por necesidad tecnica para asignar permisos a roles.

### Criterios de aceptacion

- [x] Todas las migraciones corren sin error.
- [x] Los importes usan `decimal`, no `float`.
- [x] Existen indices para numero de factura, cliente, estado, fechas y moneda.
- [x] `invoice_number` es unico.
- [x] Las relaciones criticas tienen llaves foraneas.

### Notas de validacion

- Migracion creada: `backend/database/migrations/2026_05_21_030000_create_facturapro_domain_tables.php`.
- Validacion fuerte ejecutada: `php artisan migrate:fresh`.
- Pruebas ejecutadas: `php artisan test`, 2 tests pasados.
- Validacion HTTP posterior: `GET /api/health` devuelve `{"status":"ok","app":"FacturaPro","environment":"local"}`.
- Se inspeccionaron tablas `invoices`, `invoice_items`, `invoice_payments` y `currencies` con `php artisan db:table`.
- Se habilito extension PHP `intl` en `C:\xampp\php\php.ini` porque Laravel la requiere para comandos y formato numerico/local.

## FASE 3 - Modelos Eloquent y relaciones

### Objetivo

Crear modelos con relaciones claras, casts correctos y bajo acoplamiento.

### Tareas

- [x] Crear modelos principales.
- [x] Definir `fillable` o `guarded` de forma consistente.
- [x] Definir casts de fechas, booleanos y decimales.
- [x] Definir relaciones de factura con cliente, moneda, termino, perfil fiscal, cuenta, garantia, items y pagos.
- [x] Evitar logica pesada en modelos.

### Criterios de aceptacion

- [x] Relaciones cargan correctamente.
- [x] Factura puede cargar sus lineas y pagos.
- [x] Los casts monetarios no degradan precision.

### Notas de validacion

- Se crearon modelos Eloquent para tablas de dominio.
- Se uso `fillable` explicito en modelos de dominio.
- Se agregaron casts `decimal:4`, `date`, `datetime`, `boolean`, `integer` y `array` donde aplica.
- Se valido sintaxis con `php -l` en todos los modelos.
- Se probo carga de relaciones con Tinker dentro de transaccion con rollback.
- Pruebas ejecutadas: `php artisan test`, 2 tests pasados.

## FASE 4 - Seeds iniciales

### Objetivo

Crear datos minimos operativos y seguros.

### Tareas

- [x] Usuario admin inicial.
- [x] Monedas EUR, USD y DOP.
- [x] Impuestos IVA 21%, ITBIS 18%, Tax 7% y Exento 0%.
- [x] Terminos AL CONTADO, CREDITO 15 DIAS y CREDITO 30 DIAS.
- [x] Garantias iniciales.
- [x] Cuenta bancaria profesional de prueba.
- [x] Perfil fiscal de prueba.
- [x] Texto legal inicial.
- [x] Configuracion de numeracion.
- [x] Roles ADMIN, VENDEDOR, TECNICO y LECTURA.
- [x] Permisos iniciales.

### Criterios de aceptacion

- [x] `php artisan db:seed` funciona.
- [x] Existe moneda por defecto.
- [x] Existe impuesto por defecto.
- [x] Existe usuario admin.
- [x] No se usan nombres informales o riesgosos en datos semilla.

### Notas de validacion

- Seeder actualizado: `backend/database/seeders/DatabaseSeeder.php`.
- Usuario admin local: `admin@facturapro.local`.
- Password local temporal: `FacturaPro123!`.
- El password debe cambiarse antes de cualquier despliegue real.
- Seeder ejecutado dos veces para validar idempotencia.
- Conteos validados: 1 usuario, 4 roles, 11 permisos, 3 monedas, 4 impuestos, 3 terminos, 3 garantias, 1 cuenta, 1 perfil fiscal, 1 texto legal, 1 configuracion de numeracion y 4 settings.
- Pruebas ejecutadas: `php artisan test`, 2 tests pasados.

## FASE 5 - Servicios de dominio

### Objetivo

Centralizar reglas criticas y hacerlas testeables.

### Servicios

- [x] `InvoiceCalculationService`.
- [x] `InvoiceNumberService`.
- [x] `InvoiceStatusService`.
- [x] `InvoicePdfService`.
- [x] `CurrencyFormatterService`.
- [x] `SettingsBootstrapService`.
- [x] `ActivityLogService`.

### Criterios de aceptacion

- [x] Calculos correctos con impuesto incluido y no incluido.
- [x] Facturas exentas soportadas.
- [x] Numeracion transaccional sin duplicados.
- [x] Estados calculados correctamente.
- [x] Pruebas unitarias cubren casos felices y limites.

### Notas de validacion

- Servicios creados en `backend/app/Services`.
- Calculos monetarios implementados con `brick/math`, evitando `float` para reglas de negocio.
- `InvoicePdfService` define rutas seguras y contrato inicial; generacion real con plantilla/Browsershot queda para FASE 9.
- PHPUnit configurado con SQLite en memoria para aislar pruebas y no destruir MySQL local.
- Pruebas ejecutadas: `php artisan test`, 18 tests pasados y 41 assertions.
- Sintaxis validada con `php -l` en todos los servicios.
- Validacion HTTP posterior: `GET /api/health` responde correctamente.

## FASE 6 - API REST

### Objetivo

Exponer contratos estables para web y Android.

### Endpoints minimos

- [x] `POST /api/login`.
- [x] `POST /api/logout`.
- [x] `GET /api/me`.
- [x] `GET /api/invoices`.
- [x] `POST /api/invoices`.
- [x] `GET /api/invoices/{id}`.
- [x] `PUT /api/invoices/{id}`.
- [x] `DELETE /api/invoices/{id}`.
- [x] `POST /api/invoices/{id}/issue`.
- [x] `POST /api/invoices/{id}/cancel`.
- [x] `POST /api/invoices/{id}/generate-pdf`.
- [x] `GET /api/invoices/{id}/download-pdf`.
- [x] `POST /api/invoices/{id}/mark-paid`.
- [x] CRUD de clientes.
- [x] `GET /api/settings/bootstrap`.
- [x] `GET /api/currencies`.
- [x] `GET /api/taxes`.
- [x] `GET /api/payment-terms`.
- [x] `GET /api/warranties`.
- [x] `GET /api/bank-accounts`.
- [x] `GET /api/fiscal-profiles`.

### Criterios de aceptacion

- [x] API protegida con Sanctum donde aplica.
- [x] Requests validan entrada.
- [x] Resources devuelven respuestas consistentes.
- [x] Crear y editar factura recalculan totales en backend.
- [x] Login devuelve token.
- [x] Bootstrap devuelve configuracion completa.
- [x] Editar factura respeta reglas de estado.
- [x] Emitir factura genera numero final si aplica.

### Notas de validacion

- Rutas API registradas: 26.
- Requests creados para login, clientes, facturas y pagos.
- Resources creados para clientes, facturas e items.
- `generate-pdf` existe y responde `501` hasta FASE 9, donde se implementara la generacion real.
- Pruebas ejecutadas: `php artisan test`, 29 tests pasados y 103 assertions.
- Sintaxis validada con `php -l` en controladores, requests y resources.
- Validacion HTTP local: login con `admin@facturapro.local`, `/api/me` y `/api/currencies`.

## FASE 7 - Panel web administrativo

### Objetivo

Crear panel web usable y consistente con las pantallas de referencia.

### Pantallas

- [x] Login.
- [x] Dashboard.
- [x] Listado de facturas.
- [x] Crear factura.
- [x] Editar factura.
- [x] Detalle de factura.
- [NO APLICA] Vista previa oficial. Se traslada a FASE 8 porque debe usar la plantilla HTML/CSS A4 unica.
- [x] Clientes.
- [x] Configuracion general.
- [x] Monedas.
- [x] Impuestos.
- [x] Terminos de pago.
- [x] Garantias.
- [x] Cuentas bancarias.
- [x] Perfiles fiscales.
- [x] Textos legales.
- [x] Numeracion.
- [x] Usuarios y roles.
- [x] Reportes basicos.

### Criterios de aceptacion

- [x] Flujo web permite crear borrador.
- [x] Se puede emitir factura.
- [NO APLICA] Se puede generar y descargar PDF. Pertenece a FASE 9 segun la decision tecnica base de PDF backend.
- [x] Configuracion se gestiona sin tocar codigo.

### Entregables

- [x] `docs/FASE-7-panel-web-administrativo.md`.

### Notas de validacion

- Panel web base implementado con sesiones Laravel y Blade.
- Se justifico no usar Livewire todavia: el flujo actual es server-side y la complejidad reactiva no aporta valor en esta etapa.
- Se corrigio colision de nombres entre rutas API y rutas web usando prefijo `web.*`.
- Se implemento edicion de facturas solo en estado borrador.
- Se implemento CRUD administrativo de catalogos de configuracion existentes: monedas, impuestos, terminos, garantias, cuentas, perfiles fiscales, textos legales y numeracion.
- Se implemento gestion basica de usuarios y asignacion de roles.
- Se implementaron reportes basicos por estado, cliente, moneda, vencidas y totales.
- Pruebas ejecutadas: `php artisan test`, 39 tests pasados y 143 assertions.
- Validacion de navegador local: login, dashboard, facturas y configuracion renderizan contra `http://127.0.0.1:8000` sin errores de consola.
- Validacion HTTP autenticada contra servidor limpio `http://127.0.0.1:8001`: `/settings`, `/settings/taxes`, `/users` y `/reports` responden `200`.

## FASE 8 - Plantilla HTML/CSS de factura

### Objetivo

Replicar el formato oficial con una plantilla mantenible.

### Tareas

- [x] Crear `resources/views/pdf/invoice.blade.php`.
- [x] Crear CSS A4 aislado.
- [x] Soportar logo.
- [x] Soportar filas variables sin romper layout.
- [x] Soportar moneda dinamica.
- [x] Soportar textos largos en garantia y observaciones.
- [x] Renderizar original cliente / copia vendedor.

### Criterios de aceptacion

- [x] La vista se parece a la plantilla Excel.
- [x] Renderiza correctamente en navegador.
- [x] Renderiza correctamente como PDF.
- [x] No se rompe con textos largos ni multiples lineas.

### Entregables

- [x] `docs/FASE-8-plantilla-html-css-factura.md`.

### Notas de validacion

- Se agrego ruta protegida `GET /invoices/{invoice}/preview`.
- La plantilla muestra datos calculados por backend; no recalcula importes en Blade.
- Se renderizan dos copias: original cliente y copia vendedor.
- Se valido A4 en navegador: cada copia renderiza a `794 x 1123 px`.
- Se genero PDF temporal con Chrome para validar print CSS sin integrar todavia el servicio PDF.
- `pypdf` confirmo 2 paginas A4 `595.92 x 842.88`.
- Pruebas ejecutadas: `php artisan test`, 40 tests pasados y 150 assertions.

## FASE 9 - Generacion PDF

### Objetivo

Generar PDF desde backend con trazabilidad.

### Tareas

- [x] Instalar Browsershot/Puppeteer o alternativa compatible con Windows.
- [x] Implementar `InvoicePdfService`.
- [x] Guardar PDF en `storage/app/public/invoices`.
- [x] Guardar ruta en `invoices.pdf_path`.
- [x] Registrar logs de generacion.
- [x] Crear endpoint de generacion.
- [x] Crear endpoint de descarga.

### Criterios de aceptacion

- [x] PDF se genera sin error.
- [x] PDF conserva formato.
- [x] PDF queda almacenado.
- [x] PDF se descarga protegido por permisos.

### Entregables

- [x] `docs/FASE-9-generacion-pdf.md`.

### Notas de validacion

- Se implemento generacion con Chrome/Chromium headless como alternativa compatible con Windows/XAMPP.
- El servicio usa `CHROME_PATH` si existe; si no, busca rutas comunes de Chrome, Edge y Chromium.
- El PDF final requiere factura emitida con numero.
- Se agregaron rutas web de generacion y descarga.
- Los endpoints API dejaron de responder `501` y generan/descargan PDF real.
- Se registra `invoice.pdf_generated` en `activity_logs`.
- `pypdf` confirmo PDF de 2 paginas A4 con textos clave.
- Pruebas ejecutadas: `php artisan test`, 40 tests pasados y 164 assertions.

## FASE 10 - Roles, permisos y auditoria

### Objetivo

Proteger operaciones criticas.

### Tareas

- [x] Definir roles.
- [x] Definir permisos.
- [x] Proteger rutas web.
- [x] Proteger endpoints API.
- [x] Bloquear modificaciones indebidas por estado.
- [x] Registrar creacion, edicion, emision, anulacion, pagos y generacion PDF.

### Criterios de aceptacion

- [x] Usuario sin permiso recibe 403.
- [x] Factura anulada no se modifica.
- [x] Factura pagada no permite cambios de montos.
- [x] Operaciones criticas quedan auditadas.

### Entregables

- [x] `docs/FASE-10-roles-permisos-auditoria.md`.

### Notas de validacion

- Se agrego middleware `permission:*` y alias en `bootstrap/app.php`.
- Rutas web y API de clientes, facturas, configuracion, usuarios y reportes quedaron protegidas por permisos granulares.
- Se agregaron permisos `registrar_pagos` y `gestionar_clientes`.
- Auditoria ahora recibe usuario y request en operaciones criticas de facturas.
- Se bloquea registrar pagos cuando la factura no tiene saldo pendiente.
- Validacion de rutas: `php artisan route:list --path=invoices -v`.
- Pruebas ejecutadas: `php artisan test`, 42 tests pasados y 171 assertions.

## FASE 11 - Android base

### Objetivo

Preparar la app Android para consumir la API.

### Tareas

- [x] Revisar proyecto Android existente.
- [x] Definir paquetes por capa.
- [x] Agregar Retrofit, OkHttp, DataStore y Navigation Compose.
- [x] Crear cliente API.
- [x] Crear persistencia de token.
- [x] Crear flujo de login.
- [x] Consumir `/api/settings/bootstrap`.

### Criterios de aceptacion

- [x] App compila.
- [x] Login funciona contra backend.
- [x] Token se guarda en DataStore.
- [x] Bootstrap carga configuracion.

### Entregables

- [x] `docs/FASE-11-android-base.md`.

### Notas de validacion

- Proyecto Android revisado: era una app Compose generada sin capas de datos ni cliente API.
- Se agrego arquitectura base MVVM con paquetes `data`, `domain`, `di`, `ui.auth` y `ui.home`.
- Se agrego `DataStore` para persistencia de token Sanctum.
- Se agrego Retrofit/OkHttp con interceptor Bearer.
- Se agrego Navigation Compose para flujo login/home.
- `API_BASE_URL` debug queda en `http://10.0.2.2:8001/api/` porque el backend local activo responde en `127.0.0.1:8001`.
- Validacion backend: login y `/api/settings/bootstrap` responden con el usuario admin y catalogos esperados.
- Validacion Android: `./gradlew.bat :app:testDebugUnitTest :app:assembleDebug` exitoso.
- Lint: `./gradlew.bat :app:lintDebug` exitoso con 0 errores y advertencias no bloqueantes de versiones disponibles.

## FASE 12 - Android facturas y clientes

### Objetivo

Crear flujo movil completo sin duplicar reglas criticas del backend.

### Pantallas

- [x] `LoginScreen`.
- [x] `InvoiceListScreen`.
- [x] `CreateInvoiceScreen`.
- [x] `EditInvoiceScreen`.
- [x] `InvoicePreviewScreen`.
- [x] `InvoiceDetailScreen`.
- [x] `ClientListScreen`.
- [x] `ClientFormScreen`.
- [x] `BasicSettingsScreen`.

### Criterios de aceptacion

- [x] Se crea factura desde Android.
- [x] Backend recalcula al guardar.
- [x] Se solicita PDF al backend.
- [x] Se descarga o comparte PDF desde Android.

### Entregables

- [x] `docs/FASE-12-android-facturas-y-clientes.md`.

### Notas de validacion

- Se implemento workspace autenticado con secciones `Facturas`, `Clientes` y `Ajustes`.
- Se agregaron repositorios Android para clientes y facturas, incluyendo create, detail, issue, preview HTML, generate PDF y share de PDF.
- Se agrego `FileProvider` para compartir PDF descargado temporalmente.
- La vista previa Android usa HTML oficial del backend mediante nuevo endpoint API `/api/invoices/{invoice}/preview`.
- Se ejecuto `php artisan db:seed` en backend local para sincronizar permisos del admin de desarrollo con FASE 10.
- Validacion backend: `php artisan test`, 43 tests pasados y 176 assertions.
- Validacion Android: `./gradlew.bat :app:testDebugUnitTest :app:assembleDebug :app:lintDebug` exitoso.
- Validacion de contrato local: login, bootstrap, create client, create invoice y preview HTML probados contra `127.0.0.1:8001`.

## FASE 13 - Reportes

### Objetivo

Crear reportes administrativos basicos.

### Tareas

- [x] Total facturado por fecha.
- [x] Total cobrado.
- [x] Total pendiente.
- [x] Facturas vencidas.
- [x] Facturas por estado.
- [x] Facturas por cliente.
- [x] Facturas por moneda.

### Criterios de aceptacion

- [x] Filtros por fecha y moneda.
- [x] Totales consistentes con facturas.
- [x] Consultas no provocan N+1.

### Entregables

- [x] `docs/FASE-13-reportes.md`.

### Notas de validacion

- Se extrajo la logica de reportes a `backend/app/Services/ReportService.php`.
- Se corrigio un problema de consistencia: cuando hay varias monedas, el sistema ya no muestra sumas monetarias consolidadas que mezclarian importes incompatibles.
- Los desgloses por fecha, estado y cliente ahora tambien se separan por moneda para evitar agregaciones invalidas.
- La vista web de reportes consume el nuevo contrato y agrega tabla de vencidas con acceso directo a factura.
- Pruebas web ampliadas para cubrir multi-moneda, filtros por fecha y moneda, y visibilidad de vencidas.
- Pruebas ejecutadas: `php artisan test`, 44 tests pasados y 187 assertions.

## FASE 14 - Pruebas integrales

### Objetivo

Validar sistema completo.

### Tareas

- [x] Pruebas de calculo EUR, USD y DOP.
- [x] Pruebas de impuesto 21%, 18%, 7% y 0%.
- [x] Prueba de pago parcial.
- [x] Prueba de factura pagada.
- [x] Prueba de factura vencida.
- [x] Prueba de anulacion.
- [x] Prueba de PDF.
- [x] Prueba de descarga.
- [x] Prueba Android login.
- [x] Prueba Android crear factura.

### Criterios de aceptacion

- [x] No hay errores criticos.
- [x] Calculos correctos.
- [x] PDF visualmente correcto.
- [x] Web y Android dependen de la misma logica backend.

### Entregables

- [x] `docs/FASE-14-pruebas-integrales.md`.

### Notas de validacion

- Backend: se ampliaron pruebas unitarias y API para cubrir matriz de impuestos 21%, 18%, 7% y 0%.
- Backend: se ampliaron pruebas API para matriz de monedas EUR, USD y DOP.
- Backend: se cubrieron explicitamente pago parcial, factura pagada, factura vencida, anulacion, generacion PDF y descarga PDF.
- Android: se introdujeron contratos de repositorio minimos para desacoplar ViewModel de implementaciones concretas y permitir pruebas locales repetibles.
- Android: se agregaron pruebas unitarias de `LoginViewModel` e `InvoicesViewModel` para login y creacion de factura.
- Validacion backend: `php artisan test`, 58 tests pasados y 246 assertions.
- Validacion Android: `./gradlew.bat :app:testDebugUnitTest`, `:app:assembleDebug` y `:app:lintDebug` exitosos.

## FASE 15 - Produccion

### Objetivo

Preparar despliegue en Windows VPS con Apache/XAMPP o entorno compatible.

### Tareas

- [x] Configurar `.env.production`.
- [x] Configurar `storage:link`.
- [x] Configurar permisos de carpetas.
- [x] Configurar logs.
- [x] Configurar backup de base de datos.
- [x] Configurar VirtualHost.
- [ ] Probar PDF en servidor.
- [ ] Probar Android contra API publica.

### Criterios de aceptacion

- [ ] Sistema corre en servidor.
- [ ] Login funciona.
- [ ] PDF se genera en produccion.
- [ ] No hay rutas sensibles expuestas.

### Entregables

- [x] `docs/FASE-15-produccion.md`.

### Notas de validacion

- Se agrego `backend/.env.production.example` como base de configuracion segura para produccion.
- Se agrego `backend/deploy/apache/facturapro-vhost.conf` apuntando a `backend/public`.
- Se agregaron scripts PowerShell `backend/scripts/post-deploy.ps1`, `backend/scripts/backup-database.ps1` y `backend/scripts/smoke-test-production.ps1`.
- Se agrego `android/gradle.properties.example` y la app ahora separa `API_BASE_URL` para debug y release por propiedades/variables de entorno.
- Se agrego `docs/CHECKLIST-SALIDA-PRODUCCION.md` para ejecucion operativa y verificacion final.
- Validacion local: `./gradlew.bat :app:assembleDebug :app:lintDebug` exitoso.
- Validacion de scripts PowerShell: parse correcto de `backup-database.ps1` y `post-deploy.ps1`.
- No se marcaron pruebas de PDF en servidor ni Android contra API publica porque no se proporciono un VPS, dominio ni endpoint publico real.

## Extension Android - Reportes moviles

### Objetivo

Completar Android con acceso a reportes operativos sin duplicar calculos en el dispositivo.

### Tareas

- [x] Exponer reportes por API usando la misma logica del panel web.
- [x] Proteger endpoint con permiso `ver_reportes`.
- [x] Crear DTOs/modelos Android para reportes.
- [x] Crear repositorio Android de reportes.
- [x] Crear ViewModel Android de reportes.
- [x] Crear pantalla Android de reportes con filtros de fecha y moneda.
- [x] Usar DatePicker Compose para filtros de fecha.
- [x] Integrar pestaña `Reportes` en workspace Android.

### Notas de validacion

- Endpoint agregado: `GET /api/reports`.
- Backend reutiliza `ReportService`; Android solo renderiza agregados.
- Pruebas backend agregadas en `ReportApiTest`.
- Validacion backend: `php artisan test`, 60 tests pasados y 260 assertions.
- Validacion Android: `./gradlew.bat :app:testDebugUnitTest :app:assembleDebug :app:lintDebug` exitoso.

## Registro de errores

### Formato

```text
Fecha:
Modulo:
Error:
Causa:
Solucion:
Estado:
```

```text
Fecha: 2026-05-21
Modulo: Entorno PHP / FASE 2
Error: `php artisan db:table` fallaba con "The intl PHP extension is required".
Causa: La extension `intl` estaba comentada en `C:\xampp\php\php.ini`.
Solucion: Se habilito `extension=intl` y se verifico con `php -m`.
Estado: Resuelto.
```

```text
Fecha: 2026-05-21
Modulo: Rutas web / FASE 7
Error: Redirects del panel web resolvian a rutas `/api/...` al usar nombres como `clients.index`.
Causa: Colision de nombres entre rutas API y rutas web.
Solucion: Se prefijaron los nombres de rutas web con `web.*` y se actualizaron controladores, vistas y tests.
Estado: Resuelto.
```

## Registro de decisiones

### 2026-05-20 - Laravel primero, Android despues

La logica critica debe nacer en backend. Android se conectara a contratos API estables; si se implementa Android antes, se duplicarian reglas y aumentaria el riesgo de inconsistencias.

### 2026-05-20 - PDF solo en backend

Android no generara PDF. El backend usara una plantilla HTML/CSS unica para preview y PDF, evitando divergencias visuales entre plataformas.

### 2026-05-20 - Datos historicos por snapshot

Cada factura guardara copia de moneda, cliente, emisor, impuestos y textos relevantes. Cambios futuros de configuracion no deben alterar facturas ya emitidas.

### 2026-05-21 - Pivotes de roles y permisos

Se agregaron `role_user` y `permission_role` aunque no estaban listadas inicialmente. Sin esas tablas no existe asignacion normalizada de roles a usuarios ni permisos a roles.

### 2026-05-21 - Pruebas con SQLite en memoria

PHPUnit se configuro con SQLite en memoria para que los tests de servicios sean aislados y no ejecuten `migrate:fresh` sobre la base MySQL local. MySQL se valida por separado con migraciones y ejecucion local.

### 2026-05-21 - Panel web inicial sin Livewire

FASE 7 se inicio con Blade server-side y controladores web convencionales. Livewire queda reservado para formularios dinamicos donde aporte valor real; agregarlo ahora aumentaria complejidad sin mejorar el flujo implementado.

### 2026-05-21 - Nombres de rutas web prefijados

Las rutas web usan nombres `web.*` para evitar colisiones con rutas API como `clients.index` e `invoices.show`. Sin este prefijo, los redirects y enlaces Blade podian resolver accidentalmente a `/api/...`.

### 2026-05-21 - Vista previa y PDF fuera de FASE 7

La vista previa oficial y la generacion/descarga de PDF no se cierran dentro del panel administrativo. Se mantienen en FASE 8 y FASE 9 porque deben compartir una unica plantilla HTML/CSS A4 renderizable por navegador y por el servicio PDF backend.

### 2026-05-21 - PDF con Chrome headless

FASE 9 usa Chrome/Chromium headless directamente desde `InvoicePdfService` en lugar de agregar Browsershot/Puppeteer. Es una alternativa compatible con Windows/XAMPP, reduce dependencias iniciales y mantiene la plantilla Blade como unica fuente visual.

### 2026-05-21 - Autorizacion granular con middleware propio

FASE 10 usa middleware `permission:*` sobre rutas web y API en lugar de validar permisos dentro de controladores. Esta decision mantiene los controladores centrados en reglas de negocio y deja la autorizacion como una capa transversal visible en `routes/web.php` y `routes/api.php`.

### 2026-05-21 - Android base sin Hilt todavia

FASE 11 usa un contenedor manual `AppContainer` en lugar de introducir Hilt. Para una app que apenas inicia login y bootstrap, Hilt agregaria configuracion y acoplamiento extra sin beneficio suficiente. Si en FASE 12 crece el numero de repositorios, casos de uso y ViewModels, se puede migrar a DI formal de forma controlada.

### 2026-05-21 - URL Android local en puerto 8001

La app Android apunta a `http://10.0.2.2:8001/api/` para emulador porque el backend Laravel activo responde en `127.0.0.1:8001`; `127.0.0.1:8000/api/health` devolvia 404 desde otro servicio local. Si se cambia el puerto de Laravel, debe actualizarse `API_BASE_URL` en `android/app/build.gradle.kts`.

### 2026-05-21 - Preview Android servido por backend

La vista previa movil no replica la factura en Compose. Android consume HTML oficial desde `/api/invoices/{invoice}/preview` y lo renderiza en `WebView`, reutilizando la misma plantilla Blade que usa web/PDF. Esto evita divergencias visuales y mantiene un unico contrato de plantilla.

### 2026-05-21 - Reportes monetarios separados por moneda

FASE 13 no consolida montos cuando el conjunto filtrado contiene varias monedas. Sumar DOP, USD o EUR en una sola cifra es contablemente incorrecto, asi que los KPI monetarios solo se muestran cuando el resultado pertenece a una unica moneda; en caso contrario, todos los importes se agrupan por moneda.

### 2026-05-21 - ViewModel Android desacopladas para pruebas locales

FASE 14 introduce contratos minimos de repositorio en Android para que `LoginViewModel` e `InvoicesViewModel` puedan probarse sin emulador ni backend real. Esta decision mejora testabilidad y mantiene el alcance controlado sin incorporar un framework de DI adicional.
