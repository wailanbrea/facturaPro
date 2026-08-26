# Reglas y validacion de plantillas PDF

Estado verificado: 26 de agosto de 2026.

Este documento define el comportamiento probado de factura, presupuesto e
informe. Las reglas aplican por igual al panel web y a Android porque ambos
consumen los PDF generados por el backend Laravel.

## Reglas comunes de factura y presupuesto

- La cabecera completa, logo, distintivos y numero solo aparece en la primera
  pagina de servicios.
- Las continuaciones muestran unicamente el indicador `PAGINA X DE Y`.
- Ninguna linea de servicio se elimina al paginar.
- Una descripcion consume un bloque visual por cada 78 caracteres, con un
  minimo de un bloque.
- La primera pagina admite 13 bloques visuales.
- Las continuaciones admiten hasta 24 bloques visuales antes de reservar el
  espacio requerido por los elementos finales.
- Los tres cuadros inferiores aparecen una sola vez y siempre despues de la
  ultima tabla de servicios.
- Las condiciones legales aparecen una sola vez, despues de los cuadros, y
  constituyen el ultimo contenido del documento.
- Una pagina legal separada solo se conserva cuando las condiciones no caben
  en la pagina que contiene los servicios y cuadros finales.
- El numero total mostrado debe coincidir con el numero real de paginas del
  PDF.

## Factura

- Primera pagina: hasta 13 bloques visuales, cabecera, datos, diagnostico,
  conclusiones y resumen economico.
- Una continuacion final reserva espacio a partir de 22 bloques para los tres
  cuadros y las condiciones.
- Con lineas normales, 14, 20, 25 y 35 servicios quedan en dos paginas.
- Con 36 servicios normales, la distribucion es `13 + 22 + 1` y el documento
  queda en tres paginas.
- Si una descripcion excepcional no permite compartir los cuadros, se crea
  una hoja inferior dedicada antes de las condiciones.

Matriz comprobada:

| Servicios | Distribucion | Paginas |
| ---: | --- | ---: |
| 3 | `3 + legal` | 2 |
| 14 | `13 + 1/cuadros/legal` | 2 |
| 20 | `13 + 7/cuadros/legal` | 2 |
| 25 | `13 + 12/cuadros/legal` | 2 |
| 35 | `13 + 22/cuadros/legal` | 2 |
| 36 | `13 + 22 + 1/cuadros/legal` | 3 |

## Presupuesto

- La primera pagina admite 13 bloques cuando no contiene los cuadros finales;
  si el documento termina en ella, el limite seguro con los cuadros es 10.
- La ultima continuacion admite 9 bloques junto con observaciones, pago,
  aceptacion a ancho completo y condiciones legales.
- Las continuaciones intermedias conservan una capacidad de 24 bloques.
- Cuando el ultimo bloque supera su capacidad, se mueve al menos un servicio a
  una pagina final util; nunca se crea una hoja vacia solo para los cuadros.

Matriz comprobada:

| Servicios | Distribucion | Paginas |
| ---: | --- | ---: |
| 10 | `10/cuadros + legal` | 2 |
| 11 | `10 + 1/cuadros/legal` | 2 |
| 13 | `12 + 1/cuadros/legal` | 2 |
| 14 | `13 + 1/cuadros/legal` | 2 |
| 22 | `13 + 9/cuadros/legal` | 2 |
| 23 | `13 + 9 + 1/cuadros/legal` | 3 |
| 25 | `13 + 11 + 1/cuadros/legal` | 3 |
| 37 | `13 + 23 + 1/cuadros/legal` | 3 |
| 38 | `13 + 24 + 1/cuadros/legal` | 3 |
| 46 | `13 + 24 + 9/cuadros/legal` | 3 |
| 47 | `13 + 24 + 9 + 1/cuadros/legal` | 4 |

## Informe tecnico

- Usa A4 vertical y flujo continuo del navegador.
- El logo y la cabecera aparecen una sola vez.
- Las secciones vacias no generan huecos ni paginas.
- Los informes firmados incluyen QR, distintivo de documento original y codigo
  de seguridad.
- El contenido adicional genera paginas naturales solo cuando supera A4.

## Evidencia ejecutada

- PHPUnit: 29 pruebas aprobadas y 313 aserciones.
- Android: `testDebugUnitTest` y `assembleDebug` completados correctamente.
- Playwright contra produccion: factura 3/14/20/25/35/36, presupuesto
  10/11/12/13/14/22/23/25/37/38/46/47 e informe tecnico sin overflow.
- Visor Android contra produccion:
  - `FAC-A-000003`: 14 servicios, 2 paginas.
  - `PRE-A-000002`: 12 servicios, 2 paginas.
  - `INF-A-000005`: 1 pagina.
- Los archivos activos del backend Linux se compararon por SHA-256 con el
  workspace antes del commit.
