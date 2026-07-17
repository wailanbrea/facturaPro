package com.facturador.facturapro.data.repository

import android.content.Context
import android.util.Log
import com.facturador.facturapro.BuildConfig
import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDetail
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.data.remote.dto.toRemote
import com.facturador.facturapro.data.remote.dto.toSummary
import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceSummary
import com.facturador.facturapro.domain.model.InvoiceVerification
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

class InvoiceRepository(
    private val api: FacturaProApi,
    context: Context,
) : InvoiceRepositoryContract {
    private val appContext = context.applicationContext

    override suspend fun list(search: String?): Result<List<InvoiceSummary>> = runCatching {
        api.invoices(search = search?.trim().takeUnless { it.isNullOrEmpty() }).data
            .map { it.toSummary() }
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun detail(invoiceId: Long): Result<InvoiceDetail> = runCatching {
        api.invoice(invoiceId).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun preview(invoiceId: Long): Result<String> =
        loadPreviewHtml { api.previewInvoice(invoiceId) }

    override suspend fun previewIssue(invoiceId: Long): Result<String> =
        loadPreviewHtml { api.previewInvoiceIssue(invoiceId) }

    override suspend fun previewDraft(draft: InvoiceDraft): Result<String> =
        loadPreviewHtml { api.previewInvoiceDraft(draft.toRemote()) }

    private suspend inline fun loadPreviewHtml(
        request: () -> retrofit2.Response<okhttp3.ResponseBody>,
    ): Result<String> = runCatching {
        val response = request()
        val raw = response.body()?.string().orEmpty()

        if (!response.isSuccessful) {
            val serverMessage = extractErrorMessage(raw).ifBlank {
                "HTTP ${response.code()} · ${response.message().ifBlank { "Error sin descripción" }}"
            }
            error(serverMessage)
        }

        if (raw.isBlank()) {
            error("La vista previa llegó vacía.")
        }
        raw
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(error.message ?: ApiErrorMapper.message(error), error))
        },
    )

    private fun extractErrorMessage(body: String): String {
        if (body.isBlank()) return ""
        val trimmed = body.trim()
        if (trimmed.startsWith("{")) {
            // Best-effort JSON message extraction without pulling in a parser.
            Regex("\"message\"\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"").find(trimmed)
                ?.groupValues?.getOrNull(1)
                ?.replace("\\\"", "\"")
                ?.takeIf { it.isNotBlank() }
                ?.let { return it }
        }
        return ""
    }

    override suspend fun create(draft: InvoiceDraft): Result<InvoiceDetail> = runCatching {
        api.createInvoice(draft.toRemote()).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun update(invoiceId: Long, draft: InvoiceDraft): Result<InvoiceDetail> = runCatching {
        api.updateInvoice(invoiceId, draft.toRemote()).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun issue(invoiceId: Long): Result<InvoiceDetail> = runCatching {
        api.issueInvoice(invoiceId).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun verify(number: String, code: String): Result<InvoiceVerification> = runCatching {
        api.verifyInvoice(number.trim(), code.trim().uppercase()).toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun generatePdf(invoiceId: Long): Result<String> = runCatching {
        api.generateInvoicePdf(invoiceId).pdfPath
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun downloadPdf(invoiceId: Long, fileName: String): Result<File> = withContext(Dispatchers.IO) { runCatching {
        debugLog("downloadPdf: start invoiceId=$invoiceId fileName=$fileName")
        val response = api.downloadInvoicePdf(invoiceId)
        debugLog(
            "downloadPdf: response code=${response.code()} contentType=${response.headers()["Content-Type"]} contentLength=${response.headers()["Content-Length"]}",
        )

        if (!response.isSuccessful) {
            if (response.code() == 404) {
                error("El PDF no existe en el servidor. Debe generarse nuevamente.")
            }

            error("No se pudo descargar el PDF. Código HTTP: ${response.code()}")
        }

        val body = response.body() ?: error("El servidor devolvió el PDF vacío.")
        debugLog("downloadPdf: body.contentLength=${body.contentLength()}")

        val directory = File(appContext.cacheDir, "invoices").apply {
            if (!exists() && !mkdirs()) {
                error("No se pudo crear la carpeta local de PDFs.")
            }
        }

        val safeFileName = sanitizeFileName(fileName)
            .ifBlank { "factura_$invoiceId.pdf" }
            .let { name ->
                if (name.endsWith(".pdf", ignoreCase = true)) {
                    name
                } else {
                    "$name.pdf"
                }
            }

        val target = File(directory, safeFileName)

        val copied: Long = body.use { responseBody ->
            responseBody.byteStream().use { input ->
                target.outputStream().use { output ->
                    input.copyTo(output)
                }
            }
        }
        debugLog("downloadPdf: copied=$copied target.length=${target.length()}")

        if (!target.exists()) {
            error("El PDF se descargó pero no existe en almacenamiento local.")
        }

        if (target.length() <= 0L) {
            error("El PDF se descargó vacío.")
        }

        debugLog("PDF descargado en: ${target.absolutePath} (${target.length()} bytes)")
        target
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Log.e("FacturaProPDF", "downloadPdf: exception ${error::class.java.name}: ${error.message}", error)
            Result.failure(
                IllegalStateException(
                    error.message ?: ApiErrorMapper.message(error),
                    error
                )
            )
        },
    ) }

    private fun debugLog(message: String) {
        if (BuildConfig.DEBUG) {
            Log.d("FacturaProPDF", message)
        }
    }

    private fun sanitizeFileName(fileName: String): String = buildString {
        fileName.forEach { char ->
            append(
                when {
                    char.isLetterOrDigit() -> char
                    char == '.' || char == '_' || char == '-' -> char
                    else -> '_'
                },
            )
        }
    }.ifBlank { "factura.pdf" }

    override suspend fun convert(invoiceId: Long): Result<InvoiceDetail> = runCatching {
        api.convertInvoice(invoiceId).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun markPaid(
        invoiceId: Long,
        amount: Double,
        paymentMethod: String,
        reference: String?,
        date: String
    ): Result<InvoiceDetail> = runCatching {
        api.markInvoicePaid(
            invoiceId,
            mapOf(
                "amount" to amount,
                "payment_method" to paymentMethod,
                "reference" to reference,
                "payment_date" to date
            )
        ).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
