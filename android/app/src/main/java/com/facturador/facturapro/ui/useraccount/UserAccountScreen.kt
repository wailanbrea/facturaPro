package com.facturador.facturapro.ui.useraccount

import android.content.Context
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.AdminPanelSettings
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Cloud
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Download
import androidx.compose.material.icons.outlined.ExpandLess
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Print
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Security
import androidx.compose.material.icons.outlined.SystemUpdate
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.facturador.facturapro.domain.model.AssignedCompanyInfo
import com.facturador.facturapro.domain.model.CalendarConfigSection
import com.facturador.facturapro.domain.model.CurrentUserConfig
import com.facturador.facturapro.domain.model.InvoicingConfigSection
import com.facturador.facturapro.domain.model.SystemRoleSummary
import com.facturador.facturapro.domain.model.SystemUserSummary
import com.facturador.facturapro.domain.model.SystemUsersConfigSection
import com.facturador.facturapro.domain.model.TechnicalReportsConfigSection
import com.facturador.facturapro.ui.auth.LoginUiState
import com.facturador.facturapro.ui.settings.SettingsUiState
import com.facturador.facturapro.ui.theme.OutlineVariant

@Composable
fun UserAccountScreen(
    loginState: LoginUiState,
    settingsState: SettingsUiState,
    userAccountState: UserAccountUiState,
    onRefresh: () -> Unit,
    onCheckUpdate: () -> Unit,
    onDownloadAndInstallUpdate: (Context) -> Unit,
    onInstallDownloadedApk: (Context) -> Unit,
    onUpdateSecurityPreferences: (rememberSession: Boolean, biometricEnabled: Boolean) -> Unit,
    onOpenSystemSettings: (() -> Unit)?,
    onLogout: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 16.dp, bottom = 36.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Usuario y configuraciones",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Text(
                        text = "Perfil, permisos según tu rol y actualización de la app",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                IconButton(
                    onClick = onRefresh,
                    enabled = !userAccountState.isLoadingConfig,
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Refresh,
                        contentDescription = "Actualizar configuraciones",
                        tint = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }

        // 1. Actualización directa de la APK (disponible para todos los usuarios)
        item {
            AppUpdateCard(
                state = userAccountState,
                onCheckUpdate = onCheckUpdate,
                onDownloadAndInstall = { onDownloadAndInstallUpdate(context) },
                onInstallDownloaded = { onInstallDownloadedApk(context) },
            )
        }

        // 2. Perfil del usuario actual, rol y permisos
        item {
            CurrentUserProfileCard(
                fallbackName = loginState.userName.orEmpty(),
                fallbackEmail = loginState.email,
                fallbackPermissionsCount = loginState.permissions.size,
                currentUser = userAccountState.config?.currentUser,
                isLoading = userAccountState.isLoadingConfig,
                errorMessage = userAccountState.configError,
                onRetry = onRefresh,
            )
        }

        // 3. Seguridad y sesión en este dispositivo
        item {
            DeviceSecurityCard(
                rememberSession = loginState.rememberSession,
                biometricEnabled = loginState.biometricEnabled,
                onUpdatePreferences = onUpdateSecurityPreferences,
            )
        }

        // 4. Dispositivo local (impresora Bluetooth y servidor conectado)
        item {
            DeviceStatusCard(
                settingsState = settingsState,
                onOpenSystemSettings = onOpenSystemSettings,
            )
        }

        // 5. Empresas asignadas y configuraciones de Facturación/Presupuestos (según rol)
        userAccountState.config?.let { overview ->
            if (overview.currentUser.assignedCompanies.isNotEmpty()) {
                item {
                    AssignedCompaniesCard(
                        companies = overview.currentUser.assignedCompanies,
                        hasAllCompaniesAccess = overview.currentUser.hasAllCompaniesAccess,
                    )
                }
            }

            overview.invoicingConfig?.let { invoicing ->
                item {
                    InvoicingConfigurationsCard(config = invoicing)
                }
            }

            // 6. Informes técnicos (según rol)
            overview.technicalReportsConfig?.let { reports ->
                item {
                    TechnicalReportsConfigCard(config = reports)
                }
            }

            // 7. Calendario y notificaciones (según rol)
            overview.calendarConfig?.let { calendar ->
                item {
                    CalendarConfigCard(config = calendar)
                }
            }

            // 8. Usuarios y Roles del sistema (solo ADMIN / gestionar_usuarios)
            overview.systemUsersConfig?.let { systemUsers ->
                item {
                    SystemUsersAndRolesCard(config = systemUsers)
                }
            }
        }

        // 9. Cerrar sesión
        item {
            Spacer(Modifier.height(4.dp))
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable(onClick = onLogout),
                color = MaterialTheme.colorScheme.surfaceContainerLowest,
                shape = RoundedCornerShape(14.dp),
                border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Outlined.Logout,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.error,
                        modifier = Modifier.size(20.dp),
                    )
                    Spacer(Modifier.size(12.dp))
                    Text(
                        text = "Cerrar sesión",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.error,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
fun AppUpdateCard(
    state: UserAccountUiState,
    onCheckUpdate: () -> Unit,
    onDownloadAndInstall: () -> Unit,
    onInstallDownloaded: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val hasUpdate = state.hasUpdateAvailable
    val containerColor = if (hasUpdate) {
        MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.55f)
    } else {
        MaterialTheme.colorScheme.surfaceContainer
    }

    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = containerColor),
        border = if (hasUpdate) BorderStroke(1.dp, MaterialTheme.colorScheme.primary.copy(alpha = 0.45f)) else null,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(
                    imageVector = Icons.Outlined.SystemUpdate,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(24.dp),
                )
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Actualización de la aplicación",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Text(
                        text = "Versión instalada: v${state.installedVersionName} (comp. ${state.installedVersionCode})",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        softWrap = false,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }

            if (hasUpdate && state.latestRelease != null) {
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = MaterialTheme.colorScheme.surfaceContainerLowest,
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.primary.copy(alpha = 0.3f)),
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        Text(
                            text = "Disponible: v${state.latestRelease.versionName} (comp. ${state.latestRelease.versionCode})",
                            style = MaterialTheme.typography.labelLarge,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary,
                            maxLines = 1,
                            softWrap = false,
                        )
                        if (state.latestRelease.releaseNotes.isNotBlank()) {
                            Text(
                                text = state.latestRelease.releaseNotes,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }

            if (state.isDownloadingApk) {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    LinearProgressIndicator(
                        progress = { (state.downloadProgress / 100f).coerceIn(0f, 1f) },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(6.dp),
                    )
                    Text(
                        text = "Descargando actualización: ${state.downloadProgress}%",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                        maxLines = 1,
                        softWrap = false,
                    )
                }
            }

            state.updateStatusMessage?.let { msg ->
                Text(
                    text = msg,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            state.updateErrorMessage?.let { err ->
                Text(
                    text = err,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                )
            }

            if (state.downloadedApkFile != null && !state.isDownloadingApk) {
                Button(
                    onClick = onInstallDownloaded,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Icon(Icons.Outlined.Download, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.size(8.dp))
                    Text(
                        text = if (state.needsInstallPermissionGuidance) "Instalar actualización ahora" else "Abrir instalador de APK",
                        maxLines = 1,
                        softWrap = false,
                    )
                }
            } else if (hasUpdate) {
                Button(
                    onClick = onDownloadAndInstall,
                    enabled = !state.isDownloadingApk && !state.isCheckingUpdate,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    if (state.isDownloadingApk) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                    } else {
                        Icon(Icons.Outlined.Download, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.size(8.dp))
                        Text(
                            text = "Actualizar a v${state.latestRelease?.versionName.orEmpty()}",
                            maxLines = 1,
                            softWrap = false,
                        )
                    }
                }
            }

            OutlinedButton(
                onClick = onCheckUpdate,
                enabled = !state.isCheckingUpdate && !state.isDownloadingApk,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(46.dp),
                shape = RoundedCornerShape(12.dp),
            ) {
                if (state.isCheckingUpdate) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(18.dp),
                        strokeWidth = 2.dp,
                    )
                    Spacer(Modifier.size(8.dp))
                    Text("Comprobando...", maxLines = 1, softWrap = false)
                } else {
                    Icon(Icons.Outlined.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.size(8.dp))
                    Text("Buscar actualización", maxLines = 1, softWrap = false)
                }
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun CurrentUserProfileCard(
    fallbackName: String,
    fallbackEmail: String,
    fallbackPermissionsCount: Int,
    currentUser: CurrentUserConfig?,
    isLoading: Boolean,
    errorMessage: String?,
    onRetry: () -> Unit,
) {
    var showPermissions by rememberSaveable { mutableStateOf(false) }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainer),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Badge,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(24.dp),
                )
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = currentUser?.name?.ifBlank { fallbackName } ?: fallbackName.ifBlank { "Usuario" },
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    val email = currentUser?.email?.ifBlank { fallbackEmail } ?: fallbackEmail
                    if (email.isNotBlank()) {
                        Text(
                            text = email,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }

            if (isLoading && currentUser == null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    CircularProgressIndicator(modifier = Modifier.size(22.dp), strokeWidth = 2.dp)
                }
            }

            errorMessage?.let { msg ->
                Text(
                    text = msg,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                )
                OutlinedButton(onClick = onRetry, modifier = Modifier.fillMaxWidth()) {
                    Text("Reintentar carga")
                }
            }

            currentUser?.let { user ->
                if (user.roles.isNotEmpty()) {
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
                        user.roles.forEach { role ->
                            Surface(
                                shape = RoundedCornerShape(50),
                                color = MaterialTheme.colorScheme.primary.copy(alpha = 0.12f),
                                border = BorderStroke(1.dp, MaterialTheme.colorScheme.primary.copy(alpha = 0.35f)),
                            ) {
                                Text(
                                    text = "Rol: ${role.name}",
                                    style = MaterialTheme.typography.labelMedium,
                                    fontWeight = FontWeight.SemiBold,
                                    color = MaterialTheme.colorScheme.primary,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 5.dp),
                                    maxLines = 1,
                                    softWrap = false,
                                )
                            }
                        }
                    }

                    user.roles.firstOrNull { it.description.isNotBlank() }?.let { primaryRole ->
                        Text(
                            text = primaryRole.description,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }

                HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { showPermissions = !showPermissions }
                        .padding(vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(
                        text = "Permisos activos de tu rol (${user.permissionsCount})",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                        modifier = Modifier.weight(1f),
                    )
                    Icon(
                        imageVector = if (showPermissions) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                if (showPermissions) {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        user.permissionGroups.forEach { grp ->
                            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text(
                                    text = grp.group,
                                    style = MaterialTheme.typography.labelMedium,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                Text(
                                    text = grp.permissions.joinToString(" · ") { it.name },
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                }
            } ?: run {
                if (!isLoading && errorMessage == null) {
                    Text(
                        text = "Permisos activos en sesión: $fallbackPermissionsCount",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}

@Composable
private fun DeviceSecurityCard(
    rememberSession: Boolean,
    biometricEnabled: Boolean,
    onUpdatePreferences: (rememberSession: Boolean, biometricEnabled: Boolean) -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainer),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            SectionHeaderRow(icon = Icons.Outlined.Security, title = "Seguridad y sesión")

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Recordar sesión",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = "Mantiene la sesión guardada en este teléfono",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Spacer(Modifier.size(8.dp))
                Switch(
                    checked = rememberSession,
                    onCheckedChange = { checked ->
                        onUpdatePreferences(checked, if (checked) biometricEnabled else false)
                    },
                )
            }

            HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(
                    imageVector = Icons.Outlined.Fingerprint,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(20.dp),
                )
                Spacer(Modifier.size(10.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Desbloqueo con huella o PIN",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = "Protege el acceso al abrir o reanudar la app",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Spacer(Modifier.size(8.dp))
                Switch(
                    checked = biometricEnabled,
                    onCheckedChange = { checked ->
                        onUpdatePreferences(if (checked) true else rememberSession, checked)
                    },
                )
            }

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Lock,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(16.dp),
                )
                Spacer(Modifier.size(8.dp))
                Text(
                    text = "Bloqueo automático tras 10 minutos de inactividad.",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun DeviceStatusCard(
    settingsState: SettingsUiState,
    onOpenSystemSettings: (() -> Unit)?,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainer),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            SectionHeaderRow(icon = Icons.Outlined.Cloud, title = "Dispositivo y conexión")

            ConfigDetailLine(
                icon = Icons.Outlined.Cloud,
                label = "Servidor API",
                value = settingsState.currentApiBaseUrl.ifBlank { "Predeterminado" },
            )
            ConfigDetailLine(
                icon = Icons.Outlined.Print,
                label = "Impresora Bluetooth",
                value = settingsState.selectedPrinter?.let { "${it.name} (${it.address})" } ?: "Sin impresora configurada",
            )

            if (onOpenSystemSettings != null) {
                OutlinedButton(
                    onClick = onOpenSystemSettings,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(44.dp),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Text("Abrir ajustes de servidor e impresora", maxLines = 1, softWrap = false)
                }
            }
        }
    }
}

@Composable
private fun AssignedCompaniesCard(
    companies: List<AssignedCompanyInfo>,
    hasAllCompaniesAccess: Boolean,
) {
    ExpandableConfigCard(
        icon = Icons.Outlined.Business,
        title = "Empresas habilitadas (${companies.size})",
        subtitle = if (hasAllCompaniesAccess) "Acceso a todas las empresas activas" else "Empresas asignadas a tu usuario",
        initiallyExpanded = true,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            companies.forEach { company ->
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = MaterialTheme.colorScheme.surfaceContainerLowest,
                    border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.5f)),
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        Text(
                            text = if (company.isDefault) "${company.name} (Predeterminada)" else company.name,
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.SemiBold,
                            color = MaterialTheme.colorScheme.onSurface,
                        )
                        val meta = listOfNotNull(
                            company.taxId.takeIf { it.isNotBlank() }?.let { "NIF/CIF: $it" },
                            company.city.takeIf { it.isNotBlank() },
                            "${company.logosCount} logo(s)",
                        ).joinToString(" · ")
                        if (meta.isNotBlank()) {
                            Text(
                                text = meta,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        company.nextInvoiceNumber?.let { num ->
                            Text(
                                text = "Próxima factura: $num",
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.primary,
                                maxLines = 1,
                                softWrap = false,
                            )
                        }
                        company.nextQuotationNumber?.let { num ->
                            Text(
                                text = "Próximo presupuesto: $num",
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.primary,
                                maxLines = 1,
                                softWrap = false,
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun InvoicingConfigurationsCard(config: InvoicingConfigSection) {
    ExpandableConfigCard(
        icon = Icons.Outlined.ReceiptLong,
        title = "Configuraciones de facturación",
        subtitle = "${config.currencies.size} monedas · ${config.taxes.size} impuestos · ${config.bankAccounts.size} cuentas",
        initiallyExpanded = false,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            CatalogSummaryBlock(
                title = "Monedas (${config.currencies.size})",
                lines = config.currencies.map {
                    "${it.code} (${it.symbol}) - ${it.name}" + if (it.isDefault) " ★" else ""
                },
            )
            CatalogSummaryBlock(
                title = "Impuestos (${config.taxes.size})",
                lines = config.taxes.map {
                    "${it.name} (${it.rate}%)" + if (it.isDefault) " ★" else ""
                },
            )
            CatalogSummaryBlock(
                title = "Términos de pago (${config.paymentTerms.size})",
                lines = config.paymentTerms.map {
                    "${it.name} (${it.days} días)" + if (it.isDefault) " ★" else ""
                },
            )
            CatalogSummaryBlock(
                title = "Garantías (${config.warranties.size})",
                lines = config.warranties.map {
                    "${it.title} (${it.durationMonths} meses)" + if (it.isDefault) " ★" else ""
                },
            )
            CatalogSummaryBlock(
                title = "Cuentas bancarias (${config.bankAccounts.size})",
                lines = config.bankAccounts.map {
                    val tipo = if (it.accountType == "unofficial") "No oficial" else "Oficial"
                    val holder = if (it.accountHolder.isNotBlank()) " · ${it.accountHolder}" else ""
                    "${it.label} ($tipo$holder)" + if (it.isDefault) " ★" else ""
                },
            )
            CatalogSummaryBlock(
                title = "Textos legales (${config.legalTexts.size})",
                lines = config.legalTexts.map {
                    it.name + if (it.isDefault) " ★" else ""
                },
            )
            val lockedText = if (config.effectiveLockedFields.isEmpty()) {
                "Todos los campos de texto están habilitados para tu rol."
            } else {
                "Bloqueados para tu rol: ${config.effectiveLockedFields.joinToString(", ")}"
            }
            CatalogSummaryBlock(
                title = "Bloqueo de campos en factura",
                lines = buildList {
                    add(lockedText)
                    config.systemLockedFields?.let { sysLocked ->
                        add(
                            if (sysLocked.isEmpty()) "Política global del sistema: ninguno bloqueado"
                            else "Política global del sistema: ${sysLocked.joinToString(", ")}",
                        )
                    }
                },
            )
        }
    }
}

@Composable
private fun TechnicalReportsConfigCard(config: TechnicalReportsConfigSection) {
    ExpandableConfigCard(
        icon = Icons.Outlined.Description,
        title = "Configuración de informes técnicos",
        subtitle = "Próximo número: ${config.nextNumberPreview}",
        initiallyExpanded = false,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(
                text = "Prefijo configurado: ${config.reportPrefix}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
                softWrap = false,
            )
            Text(
                text = "Próximo correlativo: ${config.nextNumberPreview}",
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
                maxLines = 1,
                softWrap = false,
            )
            if (config.sectionTitles.isNotEmpty()) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = "Secciones predeterminadas:",
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.SemiBold,
                )
                config.sectionTitles.forEachIndexed { idx, title ->
                    Text(
                        text = "${idx + 1}. $title",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}

@Composable
private fun CalendarConfigCard(config: CalendarConfigSection) {
    ExpandableConfigCard(
        icon = Icons.Outlined.CalendarMonth,
        title = "Calendario y notificaciones",
        subtitle = if (config.canManageAppointments) "Acceso completo a citas" else "Solo lectura de calendario",
        initiallyExpanded = false,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                text = "Ver calendario: ${if (config.canViewCalendar) "Habilitado" else "No"}",
                style = MaterialTheme.typography.bodySmall,
            )
            Text(
                text = "Crear / editar citas: ${if (config.canManageAppointments) "Habilitado" else "Solo lectura"}",
                style = MaterialTheme.typography.bodySmall,
            )
            Text(
                text = "Dispositivos con notificaciones push: ${config.registeredPushDevices}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun SystemUsersAndRolesCard(config: SystemUsersConfigSection) {
    ExpandableConfigCard(
        icon = Icons.Outlined.AdminPanelSettings,
        title = "Usuarios y roles del sistema",
        subtitle = "${config.users.size} usuarios · ${config.roles.size} roles activos",
        initiallyExpanded = false,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(
                text = "Usuarios (${config.users.size})",
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
            )
            config.users.forEach { user ->
                SystemUserRow(user = user)
            }

            HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))

            Text(
                text = "Roles configurados (${config.roles.size})",
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
            )
            config.roles.forEach { role ->
                SystemRoleRow(role = role)
            }
        }
    }
}

@Composable
private fun SystemUserRow(user: SystemUserSummary) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.5f)),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(3.dp),
        ) {
            Text(
                text = user.name,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Text(
                text = user.email,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                text = "Rol: ${user.roles.ifEmpty { listOf("Sin rol") }.joinToString(", ")}",
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
            if (user.assignedCompanies.isNotEmpty()) {
                Text(
                    text = "Empresas: ${user.assignedCompanies.joinToString(", ")}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text(
                text = "Actividad: ${user.invoicesCount} doc. · ${user.reportsCount} inf. · ${user.appointmentsCount} citas",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun SystemRoleRow(role: SystemRoleSummary) {
    var expanded by remember { mutableStateOf(false) }

    Surface(
        shape = RoundedCornerShape(10.dp),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.5f)),
        modifier = Modifier.clickable { expanded = !expanded },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Group,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(18.dp),
                )
                Spacer(Modifier.size(8.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = role.name,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = "${role.usersCount} usuario(s) · ${role.permissionsCount} permisos",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        softWrap = false,
                    )
                }
                Icon(
                    imageVector = if (expanded) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (role.description.isNotBlank()) {
                Text(
                    text = role.description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            if (expanded && role.permissionNames.isNotEmpty()) {
                HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))
                Text(
                    text = role.permissionNames.joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun ExpandableConfigCard(
    icon: ImageVector,
    title: String,
    subtitle: String,
    initiallyExpanded: Boolean,
    content: @Composable () -> Unit,
) {
    var expanded by rememberSaveable(title) { mutableStateOf(initiallyExpanded) }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainer),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { expanded = !expanded },
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(22.dp),
                )
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Text(
                        text = subtitle,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Icon(
                    imageVector = if (expanded) Icons.Outlined.ExpandLess else Icons.Outlined.ExpandMore,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            if (expanded) {
                HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))
                content()
            }
        }
    }
}

@Composable
private fun SectionHeaderRow(icon: ImageVector, title: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(22.dp),
        )
        Spacer(Modifier.size(10.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
        )
    }
}

@Composable
private fun ConfigDetailLine(
    icon: ImageVector,
    label: String,
    value: String,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top,
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier
                .padding(top = 2.dp)
                .size(16.dp),
        )
        Spacer(Modifier.size(8.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                text = value,
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.Medium,
                color = MaterialTheme.colorScheme.onSurface,
            )
        }
    }
}

@Composable
private fun CatalogSummaryBlock(
    title: String,
    lines: List<String>,
) {
    Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
        Text(
            text = title,
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
        )
        if (lines.isEmpty()) {
            Text(
                text = "Sin registros activos",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        } else {
            lines.forEach { line ->
                Text(
                    text = "• $line",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
