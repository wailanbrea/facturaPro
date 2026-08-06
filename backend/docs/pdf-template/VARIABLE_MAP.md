# Mapa de Variables Backend — FacturaPro

## 1. Documento: Factura / Informe Técnico (`invoice.blade.php`)

| Campo Visual | Origen Real Backend | Tipo | Nullable | Fallback Visual | Formato |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Nº Factura | `$invoice->invoice_number` | string | No | Ninguno | Textual (`FAC-2026-001`) |
| Fecha de Emisión | `$invoice->invoice_date` | date | No | Fecha actual | `d/m/Y` |
| Fecha Vencimiento | `$invoice->due_date` | date | Sí | Emisión + 30 días | `d/m/Y` |
| Estado Factura | `$invoice->status` | string | No | `Pendiente` | Texto simple (PAGADA / PENDIENTE) |
| Emisor: Nombre | `$invoice->seller_name ?? $invoice->fiscalProfile->company_name` | string | No | Datos de la empresa | Textual |
| Emisor: NIF/RNC | `$invoice->seller_tax_id ?? $invoice->fiscalProfile->tax_id` | string | No | NIF Emisor | Textual |
| Emisor: Dirección | `$invoice->seller_address ?? $invoice->fiscalProfile->address` | string | Sí | Dirección fiscal | Textual |
| Cliente: Nombre | `$invoice->client_name ?? $invoice->client->name` | string | No | Nombre cliente | Textual |
| Cliente: NIF/RNC | `$invoice->client_tax_id ?? $invoice->client->tax_id` | string | Sí | N/A | Textual |
| Cliente: Dirección | `$invoice->client_address ?? $invoice->client->address` | string | Sí | N/A | Textual |
| Tabla: Descripción | `$item->description` | string | No | Descripción ítem | Textual |
| Tabla: Cantidad | `$item->quantity` | decimal | No | `1` | Número formateado |
| Tabla: Precio Unitario | `$item->unit_price` | decimal | No | `0.00` | Servicio Moneda Backend |
| Tabla: Importe | `$item->total` | decimal | No | `0.00` | Servicio Moneda Backend |
| Subtotal | `$invoice->subtotal` | decimal | No | `0.00` | Servicio Moneda Backend |
| IVA / Impuestos | `$invoice->tax_total` | decimal | No | `0.00` | Servicio Moneda Backend |
| Importe Total | `$invoice->total` | decimal | No | `0.00` | Servicio Moneda Backend (Destacado) |
| Datos Banco (IBAN) | `$invoice->bankAccount->iban` | string | Sí | Ocultar bloque si nulo | Formato IBAN |
| Condiciones Legales | `$legalText ?? $invoice->legal_text` | string | Sí | Texto por defecto en DB | HTML o párrafos |

## 2. Documento: Presupuesto (`quotation.blade.php`)

| Campo Visual | Origen Real Backend | Tipo | Nullable | Fallback Visual | Formato |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Nº Presupuesto | `$invoice->invoice_number` | string | No | Ninguno | Textual (`PRE-2026-001`) |
| Fecha Emisión | `$invoice->invoice_date` | date | No | Fecha actual | `d/m/Y` |
| Fecha Validez | `$invoice->due_date` | date | Sí | Emisión + 15 días | `d/m/Y` |
| Emisor / Cliente | Mismos campos de `$invoice` | string | No | Misma estructura | Textual |
| Subtotal / IVA / Total | Mismos campos de `$invoice` | decimal | No | Misma estructura | Servicio Moneda Backend |
| Observaciones | `$invoice->observations` | string | Sí | N/A | Textual |
