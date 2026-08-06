# Plan de Dependencias — FacturaPro PDF Reconstruction

## Estado Actual de Dependencias

1. **Generación PDF en Producción**:
   - **Estado**: **FUNCIONAL SIN DEPENDENCIAS ADICIONALES**.
   - `InvoicePdfService.php` utiliza `Symfony\Component\Process\Process` invocado contra el binario nativo de Chrome/Edge (`msedge.exe` / `chrome.exe`) instalado en Windows.
   - **Resultado**: No se requiere instalar `spatie/laravel-pdf` ni `spatie/browsershot` en Composer, preservando la estabilidad del sistema actual.

2. **Pruebas Visuales y Capturas (Entorno de Desarrollo / Testing)**:
   - **Herramienta**: Playwright Node.js (`npx playwright`) ya está instalado globalmente en el entorno (Versión 1.62.1).
   - **Uso**: Generación de vistas previas HTML, capturas PNG de la página renderizada y comparación de imágenes sin alterar el `package.json` de producción.

## Solicitud de Aprobación

> **PROPOSAL**: No se requiere instalar ninguna nueva dependencia en `composer.json` ni en `package.json`. La generación de PDF en producción continuará utilizando el motor interno Headless Chrome/Edge en `InvoicePdfService.php`, y las pruebas de comparación visual se ejecutarán mediante Playwright.
