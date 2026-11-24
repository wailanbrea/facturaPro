package com.facturador.facturapro.data.repository

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.content.FileProvider
import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.AppReleaseInfo
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.util.concurrent.TimeUnit

sealed class ApkInstallResult {
    data object InstallerLaunched : ApkInstallResult()
    data class NeedsUnknownSourcesPermission(val settingsIntent: Intent) : ApkInstallResult()
    data class Error(val message: String) : ApkInstallResult()
}

class AppUpdateRepository(
    private val api: FacturaProApi,
    private val context: Context,
) {
    private val downloadClient = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(120, TimeUnit.SECONDS)
        .followRedirects(true)
        .followSslRedirects(true)
        .build()

    suspend fun fetchLatestRelease(): Result<AppReleaseInfo> = runCatching {
        api.appVersion().data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    suspend fun downloadApk(
        downloadUrl: String,
        onProgress: (percent: Int) -> Unit,
    ): Result<File> = withContext(Dispatchers.IO) {
        runCatching {
            val trimmedUrl = downloadUrl.trim()
            require(trimmedUrl.startsWith("https://") || trimmedUrl.startsWith("http://")) {
                "La URL de descarga de la APK no es válida."
            }

            val updatesDir = File(context.cacheDir, "updates").apply { mkdirs() }
            val targetFile = File(updatesDir, "FacturaPro-update.apk")
            if (targetFile.exists()) {
                targetFile.delete()
            }

            val request = Request.Builder()
                .url(trimmedUrl)
                .header("Accept", "application/vnd.android.package-archive, application/octet-stream, */*")
                .build()

            downloadClient.newCall(request).execute().use { response ->
                if (!response.isSuccessful) {
                    error("No se pudo descargar la actualización (HTTP ${response.code}).")
                }

                val body = response.body ?: error("El servidor devolvió una respuesta vacía.")
                val totalBytes = body.contentLength()
                var downloadedBytes = 0L
                var lastReportedPercent = -1

                body.byteStream().use { input ->
                    targetFile.outputStream().use { output ->
                        val buffer = ByteArray(16 * 1024)
                        while (true) {
                            val read = input.read(buffer)
                            if (read == -1) break
                            output.write(buffer, 0, read)
                            downloadedBytes += read
                            if (totalBytes > 0L) {
                                val percent = ((downloadedBytes * 100L) / totalBytes).toInt().coerceIn(0, 100)
                                if (percent != lastReportedPercent) {
                                    lastReportedPercent = percent
                                    onProgress(percent)
                                }
                            }
                        }
                        output.flush()
                    }
                }
            }

            if (!targetFile.exists() || targetFile.length() <= 0L) {
                error("El archivo APK descargado está vacío.")
            }

            onProgress(100)
            targetFile
        }.fold(
            onSuccess = { Result.success(it) },
            onFailure = { error ->
                Result.failure(IllegalStateException(error.message ?: ApiErrorMapper.message(error), error))
            },
        )
    }

    fun launchApkInstaller(activityContext: Context, apkFile: File): ApkInstallResult {
        if (!apkFile.exists() || apkFile.length() <= 0L) {
            return ApkInstallResult.Error("No se encontró el archivo de actualización descargado.")
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O &&
            !activityContext.packageManager.canRequestPackageInstalls()
        ) {
            val settingsIntent = Intent(
                Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:${activityContext.packageName}"),
            ).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            return ApkInstallResult.NeedsUnknownSourcesPermission(settingsIntent)
        }

        return runCatching {
            val contentUri = FileProvider.getUriForFile(
                activityContext,
                "${activityContext.packageName}.fileprovider",
                apkFile,
            )
            val installIntent = Intent(Intent.ACTION_VIEW).apply {
                setDataAndType(contentUri, "application/vnd.android.package-archive")
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            activityContext.startActivity(installIntent)
            ApkInstallResult.InstallerLaunched
        }.getOrElse { error ->
            ApkInstallResult.Error(error.message ?: "No se pudo iniciar el instalador de Android.")
        }
    }
}
