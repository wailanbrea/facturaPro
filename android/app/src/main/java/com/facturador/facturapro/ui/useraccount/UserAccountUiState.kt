package com.facturador.facturapro.ui.useraccount

import com.facturador.facturapro.BuildConfig
import com.facturador.facturapro.domain.model.AppReleaseInfo
import com.facturador.facturapro.domain.model.UserConfigurationOverview
import java.io.File

data class UserAccountUiState(
    val isLoadingConfig: Boolean = false,
    val config: UserConfigurationOverview? = null,
    val configError: String? = null,
    val installedVersionCode: Int = BuildConfig.VERSION_CODE,
    val installedVersionName: String = BuildConfig.VERSION_NAME,
    val isCheckingUpdate: Boolean = false,
    val latestRelease: AppReleaseInfo? = null,
    val updateStatusMessage: String? = null,
    val updateErrorMessage: String? = null,
    val isDownloadingApk: Boolean = false,
    val downloadProgress: Int = 0,
    val downloadedApkFile: File? = null,
    val needsInstallPermissionGuidance: Boolean = false,
) {
    val hasUpdateAvailable: Boolean
        get() = latestRelease != null &&
            latestRelease.versionCode > installedVersionCode &&
            latestRelease.downloadUrl.isNotBlank()
}
