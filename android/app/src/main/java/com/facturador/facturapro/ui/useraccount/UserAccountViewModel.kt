package com.facturador.facturapro.ui.useraccount

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.ApkInstallResult
import com.facturador.facturapro.data.repository.AppUpdateRepository
import com.facturador.facturapro.data.repository.UserConfigurationRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class UserAccountViewModel(
    private val userConfigRepository: UserConfigurationRepository,
    private val appUpdateRepository: AppUpdateRepository,
) : ViewModel() {
    private val _uiState = MutableStateFlow(UserAccountUiState())
    val uiState: StateFlow<UserAccountUiState> = _uiState.asStateFlow()

    fun refreshAll() {
        loadUserConfigurations()
        checkForAppUpdate(silentIfUpToDate = true)
    }

    fun loadUserConfigurations() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoadingConfig = true, configError = null) }
            userConfigRepository.load().fold(
                onSuccess = { overview ->
                    _uiState.update {
                        it.copy(
                            isLoadingConfig = false,
                            config = overview,
                            configError = null,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoadingConfig = false,
                            configError = error.message ?: "No se pudieron cargar las configuraciones.",
                        )
                    }
                },
            )
        }
    }

    fun checkForAppUpdate(silentIfUpToDate: Boolean = false) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isCheckingUpdate = true,
                    updateErrorMessage = null,
                    updateStatusMessage = if (silentIfUpToDate) it.updateStatusMessage else null,
                )
            }

            appUpdateRepository.fetchLatestRelease().fold(
                onSuccess = { release ->
                    val currentCode = _uiState.value.installedVersionCode
                    val statusMsg = when {
                        release.versionCode > currentCode ->
                            "Nueva versión disponible: v${release.versionName} (compilación ${release.versionCode})."
                        silentIfUpToDate -> null
                        else -> "Ya tienes la última versión instalada (v${_uiState.value.installedVersionName})."
                    }
                    _uiState.update {
                        it.copy(
                            isCheckingUpdate = false,
                            latestRelease = release,
                            updateStatusMessage = statusMsg,
                            updateErrorMessage = null,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isCheckingUpdate = false,
                            updateErrorMessage = if (silentIfUpToDate) null else {
                                error.message ?: "No se pudo comprobar si hay actualizaciones."
                            },
                        )
                    }
                },
            )
        }
    }

    fun downloadAndInstallUpdate(activityContext: Context) {
        val release = _uiState.value.latestRelease ?: return
        if (release.downloadUrl.isBlank()) {
            _uiState.update { it.copy(updateErrorMessage = "No hay enlace de descarga configurado para esta versión.") }
            return
        }

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isDownloadingApk = true,
                    downloadProgress = 0,
                    updateErrorMessage = null,
                    updateStatusMessage = "Descargando versión v${release.versionName}...",
                    needsInstallPermissionGuidance = false,
                )
            }

            appUpdateRepository.downloadApk(
                downloadUrl = release.downloadUrl,
                onProgress = { percent ->
                    _uiState.update { it.copy(downloadProgress = percent) }
                },
            ).fold(
                onSuccess = { file ->
                    _uiState.update {
                        it.copy(
                            isDownloadingApk = false,
                            downloadProgress = 100,
                            downloadedApkFile = file,
                            updateStatusMessage = "Descarga completada. Iniciando instalador...",
                        )
                    }
                    installDownloadedApk(activityContext)
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isDownloadingApk = false,
                            updateErrorMessage = error.message ?: "No se pudo descargar la actualización.",
                            updateStatusMessage = null,
                        )
                    }
                },
            )
        }
    }

    fun installDownloadedApk(activityContext: Context) {
        val apkFile = _uiState.value.downloadedApkFile ?: return
        when (val result = appUpdateRepository.launchApkInstaller(activityContext, apkFile)) {
            ApkInstallResult.InstallerLaunched -> {
                _uiState.update {
                    it.copy(
                        needsInstallPermissionGuidance = false,
                        updateErrorMessage = null,
                        updateStatusMessage = "Confirma la actualización en el instalador de Android.",
                    )
                }
            }
            is ApkInstallResult.NeedsUnknownSourcesPermission -> {
                _uiState.update {
                    it.copy(
                        needsInstallPermissionGuidance = true,
                        updateStatusMessage = "Activa \"Permitir desde esta fuente\" para FacturaPro y luego pulsa \"Instalar actualización\".",
                        updateErrorMessage = null,
                    )
                }
                runCatching { activityContext.startActivity(result.settingsIntent) }
            }
            is ApkInstallResult.Error -> {
                _uiState.update {
                    it.copy(
                        updateErrorMessage = result.message,
                        updateStatusMessage = null,
                    )
                }
            }
        }
    }

    companion object {
        fun factory(
            userConfigRepository: UserConfigurationRepository,
            appUpdateRepository: AppUpdateRepository,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(UserAccountViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }
                return UserAccountViewModel(userConfigRepository, appUpdateRepository) as T
            }
        }
    }
}
