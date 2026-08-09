PROYECTO: FACTURAPRO

FacturaPro es un sistema integral de facturación compuesto por una aplicación web administrativa, un backend Laravel con API REST y una aplicación móvil Android desarrollada en Kotlin.

El objetivo principal del sistema es permitir que técnicos, vendedores, autónomos y pequeñas empresas puedan crear, administrar, visualizar, descargar, imprimir y compartir facturas, cotizaciones e informes técnicos profesionales.

El sistema debe ser configurable, escalable y preparado para trabajar con distintos perfiles fiscales, monedas, impuestos, cuentas bancarias, garantías, términos de pago y diseños de documentos PDF.

==================================================
ARQUITECTURA GENERAL
==================================================

Backend:

- Laravel.
- API REST.
- MySQL.
- Laravel Sanctum para autenticación móvil.
- Servicios de dominio para cálculos, numeración y generación de documentos.
- Generación de PDF desde el servidor.

Aplicación web:

- Panel administrativo.
- Gestión de facturas.
- Gestión de cotizaciones.
- Gestión de informes técnicos.
- Gestión de clientes.
- Panel de configuración.
- Vista previa de documentos.
- Descarga e impresión de PDF.

Aplicación móvil:

- Android.
- Kotlin.
- Jetpack Compose.
- MVVM.
- Retrofit.
- Coroutines.
- StateFlow.
- La aplicación consume la API Laravel.

El móvil no debe generar directamente los PDF.

El backend debe generar los documentos para asegurar que la versión web y la aplicación móvil utilicen exactamente el mismo formato.

==================================================
OBJETIVO DEL SISTEMA
==================================================

FacturaPro debe permitir:

- Crear facturas.
- Crear cotizaciones o presupuestos.
- Crear informes técnicos.
- Guardar documentos como borrador.
- Emitir documentos.
- Visualizar los documentos antes de generarlos.
- Generar PDF.
- Descargar PDF.
- Imprimir PDF.
- Compartir PDF desde Android.
- Consultar documentos anteriores.
- Buscar y filtrar documentos.
- Registrar pagos.
- Controlar balances pendientes.
- Gestionar clientes.
- Configurar todos los datos administrativos sin modificar código.

==================================================
DOCUMENTOS DEL SISTEMA
==================================================

FacturaPro manejará tres tipos principales de documentos:

1. Factura.
2. Cotización o presupuesto.
3. Informe técnico.

Cada tipo de documento debe tener su propia plantilla PDF.

Rutas previstas:

resources/views/pdf/invoice.blade.php
resources/views/pdf/quotation.blade.php
resources/views/pdf/report.blade.php

Las plantillas deben estar separadas porque cada documento tiene una estructura visual y funcional diferente.

==================================================
FACTURAS
==================================================

Las facturas permiten registrar servicios, productos, reparaciones y trabajos técnicos.

Campos principales:

- Tipo de documento.
- Número de factura.
- Fecha de emisión.
- Fecha de vencimiento.
- Término de pago.
- Cliente.
- Identificación fiscal del cliente.
- Dirección del cliente.
- Perfil fiscal del emisor.
- Moneda.
- Cuenta bancaria.
- Garantía.
- Observaciones.
- Importe recibido.
- Preparado por.
- Recibido por.
- Texto legal.
- Estado de la factura.

Cada línea de factura contiene:

- Descripción.
- Cantidad.
- Costo unitario.
- Impuesto.
- Precio unitario.
- Importe.

El sistema calcula automáticamente:

- Subtotal por línea.
- Impuesto por línea.
- Precio unitario con impuesto.
- Importe por línea.
- Subtotal general.
- Impuesto total.
- Total a pagar.
- Balance pendiente.

El backend siempre debe recalcular los totales.

La aplicación web o Android nunca debe enviar totales como valores confiables.

Estados posibles:

- Borrador.
- Emitida.
- Parcialmente pagada.
- Pagada.
- Vencida.
- Anulada.

==================================================
COTIZACIONES Y PRESUPUESTOS
==================================================

Las cotizaciones permiten presentar al cliente una propuesta de trabajos, servicios, materiales y precios antes de emitir una factura.

Campos principales:

- Número de presupuesto.
- Fecha de emisión.
- Fecha de validez.
- Cliente.
- Perfil fiscal del emisor.
- Forma de pago.
- Técnico asignado.
- Referencia u obra.
- Lugar de intervención.
- Alcance del servicio.
- Observaciones.
- Condiciones de aceptación.
- Cuenta bancaria.
- Moneda.
- Impuesto.
- Descuento.
- Total presupuestado.

Cada línea contiene:

- Descripción.
- Cantidad.
- Precio unitario.
- Importe.

La plantilla del presupuesto debe ser independiente de la factura.

Debe permitir convertir una cotización aprobada en factura en el futuro.

==================================================
INFORMES TÉCNICOS
==================================================

El módulo Informes permite crear documentos técnicos independientes de las facturas y presupuestos.

Cada informe contiene:

- Número de informe.
- Fecha.
- Perfil fiscal o emisor.
- Cliente o destinatario.
- Dirección.
- Título del documento.
- Cuatro secciones principales.
- Contenido editable para cada sección.
- Observaciones internas.
- Estado.
- PDF generado.

Las cuatro secciones iniciales son:

1. Diagnóstico de la Avería.
2. Acciones Realizadas.
3. Análisis de Combustión Resultados Post-Reparación.
4. Conclusión Técnica.

Los cuatro títulos deben ser configurables desde el panel de configuración.

Al crear un informe, el sistema debe copiar esos títulos dentro del documento.

Si posteriormente se modifica la configuración, los informes antiguos no deben cambiar.

Los títulos también pueden editarse individualmente dentro de cada informe.

==================================================
CLIENTES
==================================================

El sistema debe permitir:

- Crear clientes.
- Editar clientes.
- Buscar clientes.
- Seleccionar clientes al crear documentos.
- Consultar sus facturas.
- Consultar sus cotizaciones.
- Consultar sus informes.
- Ver balances pendientes.

Campos del cliente:

- Nombre.
- Identificación fiscal.
- Dirección.
- Ciudad.
- Teléfono.
- Email.
- Notas.

Cuando se crea un documento, se debe guardar un snapshot de los datos importantes del cliente.

Esto evita que documentos antiguos cambien si posteriormente se modifica el cliente.

==================================================
PERFILES FISCALES
==================================================

El sistema debe permitir varios perfiles fiscales o emisores.

Cada perfil fiscal contiene:

- Nombre fiscal.
- CIF.
- RNC.
- NIF.
- Tax ID.
- Dirección.
- Ciudad.
- Teléfono.
- Email.
- Logo.
- Estado activo.
- Perfil predeterminado.

Cada factura, cotización o informe debe guardar una copia de los datos fiscales utilizados al momento de emitirse.

==================================================
MONEDAS
==================================================

El sistema debe soportar múltiples monedas.

Monedas iniciales:

- Euro.
- Dólar estadounidense.
- Peso dominicano.

Códigos:

- EUR.
- USD.
- DOP.

Cada moneda debe permitir configurar:

- Nombre.
- Código ISO.
- Símbolo.
- Separador decimal.
- Separador de miles.
- Cantidad de decimales.
- Posición del símbolo.
- Estado activo.
- Moneda predeterminada.

Cada documento debe guardar el formato de moneda utilizado al momento de crearse.

==================================================
IMPUESTOS
==================================================

Los impuestos deben ser configurables.

Ejemplos iniciales:

- IVA 21%.
- ITBIS 18%.
- Tax 7%.
- Exento 0%.

Cada impuesto contiene:

- Nombre.
- Porcentaje.
- Estado activo.
- Impuesto predeterminado.

El sistema debe permitir:

- Impuestos diferentes por línea.
- Productos o servicios exentos.
- Precios con impuesto incluido.
- Precios sin impuesto incluido.

==================================================
TÉRMINOS DE PAGO
==================================================

Los términos de pago deben configurarse desde el sistema.

Ejemplos:

- Al contado.
- Crédito 15 días.
- Crédito 30 días.

Cada término contiene:

- Nombre.
- Cantidad de días.
- Descripción.
- Estado activo.
- Predeterminado.

Al seleccionar un término, el vencimiento debe calcularse automáticamente.

==================================================
GARANTÍAS
==================================================

Las garantías deben configurarse desde el panel.

Ejemplos:

- Garantía de 6 meses.
- Garantía de 1 año.
- Garantía de 3 años.

Cada garantía contiene:

- Título.
- Duración en meses.
- Descripción.
- Texto completo.
- Estado activo.
- Predeterminada.

La garantía puede seleccionarse al crear una factura y su texto puede modificarse dentro del documento.

==================================================
CUENTAS BANCARIAS
==================================================

El sistema debe permitir varias cuentas bancarias.

Cada cuenta contiene:

- Nombre visible.
- Tipo.
- Titular.
- Banco.
- Número de cuenta.
- IBAN.
- Swift o BIC.
- Moneda.
- Estado activo.
- Predeterminada.

Tipos profesionales:

- Oficial.
- Principal.
- Secundaria.
- Personal.
- Alternativa.

Las plantillas actuales deben mostrar únicamente la forma de pago por transferencia bancaria.

No deben mostrar Bizum, tarjeta ni efectivo en los nuevos diseños.

==================================================
CONFIGURACIÓN GENERAL
==================================================

El panel de configuración debe permitir modificar:

- Nombre del sistema.
- Logo.
- Perfiles fiscales.
- Monedas.
- Impuestos.
- Términos de pago.
- Garantías.
- Cuentas bancarias.
- Numeración de facturas.
- Numeración de presupuestos.
- Numeración de informes.
- Textos legales.
- Títulos de informes.
- Condiciones generales.
- Configuración de PDF.
- Datos predeterminados.

Todo lo que pueda cambiar según empresa, país o cliente debe estar en configuración y no escrito directamente en código.

==================================================
NUMERACIÓN
==================================================

La numeración debe ser segura y configurable.

Ejemplos:

Facturas:
FAC-2026-000001

Presupuestos:
PRE-2026-000001

Informes:
INF-2026-000001

Debe permitir:

- Prefijo.
- Serie.
- Año.
- Número siguiente.
- Cantidad de ceros.
- Reinicio anual.
- Reinicio mensual.
- Número manual opcional.

No deben existir números duplicados.

La numeración debe generarse con transacciones o bloqueo de base de datos.

==================================================
DISEÑO DE LOS PDF
==================================================

Los PDF deben generarse desde Laravel usando Blade, HTML y CSS.

Flujo:

Datos de MySQL
→ Laravel
→ Blade
→ Chromium
→ PDF

Se recomienda utilizar:

- Spatie Laravel PDF.
- Browsershot.
- Puppeteer o Chromium.

Los PDF deben ser:

- Profesionales.
- Legibles.
- Consistentes.
- Editables desde código.
- Generados con datos dinámicos.
- Compatibles con impresión.
- Compatibles con web y Android.

Las imágenes de referencia sirven únicamente para reconstruir el diseño.

No se deben usar como fondo completo del PDF.

==================================================
NUEVO DISEÑO DE FACTURA
==================================================

La nueva factura debe tener dos páginas A4 horizontales.

Página 1:

- Logo grande.
- Indicadores de servicio.
- Título Factura / Informe de Intervención Técnica.
- Número de factura.
- QR real opcional.
- Datos fiscales del emisor.
- Datos del cliente.
- Equipo objeto de la intervención.
- Datos de la factura.
- Tabla de actuaciones técnicas.
- Diagnóstico técnico.
- Conclusiones técnicas.
- Resumen económico.
- Importe total.
- Aceptación de la intervención.
- Transferencia bancaria.
- Nota legal.

Página 2:

- Garantía legal.
- Exclusiones.
- Limitaciones.
- Daños ocultos.
- Equipos antiguos.
- Presupuestos.
- Protección de datos.
- Facturación y pago.
- Aceptación.
- Jurisdicción.
- Mensaje ecológico.

No debe mostrar:

- Teléfono del emisor dentro de datos fiscales.
- Email del emisor.
- Página web del emisor.
- Columna Categoría.
- Bizum.
- Tarjeta.
- Efectivo.
- Redes sociales.
- Footer comercial.
- Bloque grande separado de estado Pagada.

==================================================
NUEVO DISEÑO DE PRESUPUESTO
==================================================

El presupuesto debe tener dos páginas A4 horizontales.

Página 1:

- Logo grande.
- Indicadores de servicio.
- Título Presupuesto.
- Número.
- QR real opcional.
- Datos fiscales del emisor.
- Datos del cliente.
- Detalles del presupuesto.
- Resumen del presupuesto.
- Tabla de trabajos y suministros.
- Alcance del servicio.
- Lista de elementos incluidos.
- Observaciones.
- Transferencia bancaria.
- Aceptación del presupuesto.

Página 2:

- Garantía.
- Validez.
- Condiciones.
- Plazos de ejecución.
- Materiales.
- Modificaciones.
- Protección de datos.
- Cancelación.
- Aceptación.
- Jurisdicción.
- Mensaje ecológico.

No debe mostrar:

- Contactos del emisor.
- Columna Categoría.
- Bizum.
- Tarjeta.
- Efectivo.
- Redes sociales.
- Footer comercial.

==================================================
APP ANDROID
==================================================

La aplicación Android debe permitir:

- Iniciar sesión.
- Consultar facturas.
- Crear facturas.
- Editar borradores.
- Consultar cotizaciones.
- Crear cotizaciones.
- Consultar informes.
- Crear informes.
- Seleccionar clientes.
- Seleccionar moneda.
- Seleccionar impuesto.
- Seleccionar garantía.
- Seleccionar cuenta bancaria.
- Ver vista previa.
- Solicitar generación de PDF.
- Descargar PDF.
- Compartir PDF.

La aplicación no calcula totales definitivos.

Puede mostrar cálculos provisionales, pero el backend debe recalcular todo al guardar.

==================================================
REGLAS CRÍTICAS DEL PROYECTO
==================================================

1. MySQL es la base de datos principal.
2. Laravel es la fuente de verdad.
3. El backend recalcula todos los importes.
4. Web y Android consumen la misma lógica.
5. Los PDF se generan únicamente en el backend.
6. No duplicar lógica de negocio.
7. No inventar campos sin revisar modelos y migraciones.
8. No quemar configuraciones en código.
9. Los documentos emitidos deben guardar snapshots.
10. Las plantillas PDF deben permanecer separadas.
11. No romper módulos existentes.
12. No eliminar campos sin confirmar.
13. Usar valores monetarios DECIMAL, nunca FLOAT o DOUBLE.
14. Todo cambio debe probarse.
15. Todo desarrollo debe quedar documentado.

==================================================
PRIORIDAD ACTUAL
==================================================

La prioridad actual del proyecto es:

1. Perfeccionar la generación de PDF.
2. Crear una plantilla independiente para factura.
3. Crear una plantilla independiente para presupuesto.
4. Crear la plantilla de informes técnicos.
5. Integrar correctamente los datos existentes.
6. Mantener compatibilidad con EUR, USD y DOP.
7. Garantizar que los PDF puedan verse, descargarse, imprimirse y compartirse.
8. Mejorar el diseño sin alterar la lógica financiera existente.

Este es el contexto general permanente del proyecto FacturaPro.
Antes de realizar cualquier cambio, revisa la estructura real del proyecto, los modelos, migraciones, servicios, controladores, rutas y plantillas existentes.
