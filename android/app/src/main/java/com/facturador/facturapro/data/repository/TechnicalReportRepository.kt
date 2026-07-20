package com.facturador.facturapro.data.repository

import android.content.Context
import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDetail
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.data.remote.dto.toRemote
import com.facturador.facturapro.data.remote.dto.toSummary
import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportDraft
import com.facturador.facturapro.domain.model.TechnicalReportSetting
import com.facturador.facturapro.domain.model.TechnicalReportSummary
import java.io.File
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class TechnicalReportRepository(
    private val api: FacturaProApi,
    context: Context,
) : TechnicalReportRepositoryContract {
    private val appContext = context.applicationContext

    override suspend fun settings(fiscalProfileId: Long?): Result<TechnicalReportSetting> = runCatching {
        api.reportSettings(fiscalProfileId).data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun list(search: String?): Result<List<TechnicalReportSummary>> = runCatching {
        api.technicalReports(search = search?.trim().takeUnless { it.isNullOrEmpty() }).data
            .map { it.toSummary() }
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun detail(reportId: Long): Result<TechnicalReportDetail> = runCatching {
        api.technicalReport(reportId).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun preview(reportId: Long): Result<String> = runCatching {
        val response = api.previewTechnicalReport(reportId)
        val raw = response.body()?.string().orEmpty()
        if (!response.isSuccessful) {
            error(extractErrorMessage(raw).ifBlank { "HTTP ${response.code()}" })
        }
        if (raw.isBlank()) {
            error("La vista previa llego vacia.")
        }
        raw
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(it.message ?: ApiErrorMapper.message(it), it)) },
    )

    override suspend fun create(draft: TechnicalReportDraft): Result<TechnicalReportDetail> = runCatching {
        api.createTechnicalReport(draft.toRemote()).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun update(reportId: Long, draft: TechnicalReportDraft): Result<TechnicalReportDetail> = runCatching {
        api.updateTechnicalReport(reportId, draft.toRemote()).data.toDetail()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun deleteOrCancel(reportId: Long): Result<Unit> = runCatching {
        val response = api.deleteTechnicalReport(reportId)
        if (!response.isSuccessful) {
            error("No se pudo eliminar o anular el informe. Codigo HTTP: ${response.code()}")
        }
    }.fold(
        onSuccess = { Result.success(Unit) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun generatePdf(reportId: Long): Result<String> = runCatching {
        api.generateTechnicalReportPdf(reportId).pdfPath
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { Result.failure(IllegalStateException(ApiErrorMapper.message(it), it)) },
    )

    override suspend fun downloadPdf(reportId: Long, fileName: String): Result<File> = withContext(Dispatchers.IO) {
        runCatching {
            val response = api.downloadTechnicalReportPdf(reportId)
            if (!response.isSuccessful) {
                error("No se pudo descargar el PDF. Codigo HTTP: ${response.code()}")
            }

            val body = response.body() ?: error("El servidor devolvio el PDF vacio.")
            val directory = File(appContext.cacheDir, "technical-reports").apply {
                if (!exists() && !mkdirs()) {
                    error("No se pudo crear la carpeta local de informes.")
                }
            }
            val safeName = sanitizeFileName(fileName).ifBlank { "informe_$reportId.pdf" }
                .let { if (it.endsWith(".pdf", ignoreCase = true)) it else "$it.pdf" }
            val target = File(directory, safeName)

            body.use { responseBody ->
                responseBody.byteStream().use { input ->
                    target.outputStream().use { output -> input.copyTo(output) }
                }
            }

            if (!target.exists() || target.length() <= 0L) {
                error("El PDF se descargo vacio.")
            }

            target
        }.fold(
            onSuccess = { Result.success(it) },
            onFailure = { Result.failure(IllegalStateException(it.message ?: ApiErrorMapper.message(it), it)) },
        )
    }

    private fun extractErrorMessage(body: String): String {
        if (body.isBlank()) return ""
        val trimmed = body.trim()
        if (!trimmed.startsWith("{")) return ""

        return Regex("\"message\"\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"")
            .find(trimmed)
            ?.groupValues
            ?.getOrNull(1)
            ?.replace("\\\"", "\"")
            .orEmpty()
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
    }
}
