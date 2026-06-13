package com.facturador.facturapro.ui.verification

import android.content.Context
import android.net.Uri
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlinx.coroutines.suspendCancellableCoroutine

/**
 * Parsed contents of a verification QR code.
 */
data class ScannedCredentials(
    val number: String,
    val code: String,
)

/**
 * Launches Google's built-in code scanner (handles camera + permission +
 * on-demand module download itself) and returns the raw QR value, or null if
 * the user cancelled.
 */
suspend fun scanVerificationQr(context: Context): String? = suspendCancellableCoroutine { continuation ->
    val options = GmsBarcodeScannerOptions.Builder()
        .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
        .build()

    GmsBarcodeScanning.getClient(context, options)
        .startScan()
        .addOnSuccessListener { barcode -> continuation.resume(barcode.rawValue) }
        .addOnCanceledListener { continuation.resume(null) }
        .addOnFailureListener { error -> continuation.resumeWithException(error) }
}

/**
 * Extracts the invoice number and security code from a scanned QR value.
 *
 * Accepts the verification URL printed on the document
 * (`.../invoices/verify?number=...&code=...`). Returns null when the QR does
 * not carry both fields.
 */
fun parseVerificationPayload(raw: String?): ScannedCredentials? {
    if (raw.isNullOrBlank()) return null

    val uri = runCatching { Uri.parse(raw.trim()) }.getOrNull() ?: return null
    val number = uri.getQueryParameter("number")?.trim()
    val code = uri.getQueryParameter("code")?.trim()

    if (number.isNullOrEmpty() || code.isNullOrEmpty()) return null

    return ScannedCredentials(number = number, code = code)
}
