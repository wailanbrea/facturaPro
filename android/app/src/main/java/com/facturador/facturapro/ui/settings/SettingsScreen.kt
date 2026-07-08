package com.facturador.facturapro.ui.settings

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.BarChart
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Cloud
import androidx.compose.material.icons.outlined.Print
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.ui.auth.LoginUiState
import com.facturador.facturapro.ui.theme.OutlineVariant

@Composable
fun SettingsScreen(
    state: LoginUiState,
    settingsState: SettingsUiState,
    onRetryBootstrap: () -> Unit,
    onOpenReports: () -> Unit = {},
    onLoadPrinters: () -> Unit = {},
    onSavePrinter: (PrinterDevice) -> Unit = {},
    onClearPrinter: () -> Unit = {},
    onServerUrlChanged: (String) -> Unit = {},
    onSaveServerUrl: () -> Unit = {},
    onResetServerUrl: () -> Unit = {},
    onBluetoothPermissionsDenied: () -> Unit = {},
    onLogout: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val bluetoothPermissions = remember { requiredBluetoothPermissions() }
    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions(),
    ) { permissions ->
        val allGranted = bluetoothPermissions.all { permission ->
            permissions[permission] == true ||
                ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED
        }

        if (allGranted) {
            onLoadPrinters()
        } else {
            onBluetoothPermissionsDenied()
        }
    }

    fun requestPrinters() {
        val missingPermissions = bluetoothPermissions.filter { permission ->
            ContextCompat.checkSelfPermission(context, permission) != PackageManager.PERMISSION_GRANTED
        }

        if (missingPermissions.isEmpty()) {
            onLoadPrinters()
        } else {
            permissionLauncher.launch(missingPermissions.toTypedArray())
        }
    }

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 16.dp, bottom = 32.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Column {
                Text(
                    text = "Ajustes",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = state.userName ?: "Catálogos de la empresa",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        item {
            ActionRow(
                title = "Reportes",
                subtitle = "Métricas y análisis del período",
                onClick = onOpenReports,
            )
        }

        item {
            ServerSettingsCard(
                state = settingsState,
                onServerUrlChanged = onServerUrlChanged,
                onSaveServerUrl = onSaveServerUrl,
                onResetServerUrl = onResetServerUrl,
            )
        }

        item {
            PrinterSettingsCard(
                state = settingsState,
                onLoadPrinters = ::requestPrinters,
                onSavePrinter = onSavePrinter,
                onClearPrinter = onClearPrinter,
            )
        }

        if (state.isBootstrapLoading) {
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 24.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.Center,
                ) {
                    CircularProgressIndicator()
                }
            }
        }

        state.errorMessage?.takeIf { state.isAuthenticated }?.let { message ->
            item {
                ErrorState(message = message, onRetry = onRetryBootstrap)
            }
        }

        state.bootstrap?.let { bootstrap ->
            item {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = "Catálogos",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
            }
            items(catalogRows(bootstrap)) { row ->
                CatalogSummaryCard(
                    title = row.title,
                    count = row.count,
                    detail = row.detail,
                )
            }
        }

        item {
            Spacer(Modifier.height(8.dp))
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
private fun ServerSettingsCard(
    state: SettingsUiState,
    onServerUrlChanged: (String) -> Unit,
    onSaveServerUrl: () -> Unit,
    onResetServerUrl: () -> Unit,
) {
    Card(
        shape = RoundedCornerShape(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceContainer,
        ),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Cloud,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(22.dp),
                )
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Servidor",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Text(
                        text = state.currentApiBaseUrl.ifBlank { "Sin servidor configurado" },
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            OutlinedTextField(
                value = state.serverUrlInput,
                onValueChange = onServerUrlChanged,
                modifier = Modifier.fillMaxWidth(),
                label = { Text("URL de API") },
                placeholder = { Text("facturapro.bsolutions.dev") },
                singleLine = true,
                enabled = !state.isSavingServerUrl,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
            )

            Text(
                text = "Predeterminado: https://facturapro.bsolutions.dev/api/",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button(
                    onClick = onSaveServerUrl,
                    enabled = !state.isSavingServerUrl && state.serverUrlInput.isNotBlank(),
                    modifier = Modifier.weight(1f),
                ) {
                    if (state.isSavingServerUrl) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                    } else {
                        Text("Guardar")
                    }
                }

                OutlinedButton(
                    onClick = onResetServerUrl,
                    enabled = !state.isSavingServerUrl,
                    modifier = Modifier.weight(1f),
                ) {
                    Text("Restaurar")
                }
            }

            state.serverMessage?.let { message ->
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun ActionRow(
    title: String,
    subtitle: String,
    onClick: () -> Unit,
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(14.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(
                imageVector = Icons.Outlined.BarChart,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(22.dp),
            )
            Spacer(Modifier.size(14.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Icon(
                imageVector = Icons.Outlined.ChevronRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun PrinterSettingsCard(
    state: SettingsUiState,
    onLoadPrinters: () -> Unit,
    onSavePrinter: (PrinterDevice) -> Unit,
    onClearPrinter: () -> Unit,
) {
    Card(
        shape = RoundedCornerShape(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceContainer,
        ),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Print,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(22.dp),
                )
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Impresora",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    Text(
                        text = state.selectedPrinter?.let { "${it.name} (${it.address})" } ?: "Sin impresora configurada",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button(
                    onClick = onLoadPrinters,
                    enabled = !state.isLoadingPrinters,
                    modifier = Modifier.weight(1f),
                ) {
                    if (state.isLoadingPrinters) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                    } else {
                        Text("Buscar")
                    }
                }

                OutlinedButton(
                    onClick = onClearPrinter,
                    enabled = state.selectedPrinter != null && !state.isLoadingPrinters,
                    modifier = Modifier.weight(1f),
                ) {
                    Text("Quitar")
                }
            }

            state.printerMessage?.let { message ->
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            state.availablePrinters.forEach { printer ->
                PrinterDeviceRow(
                    printer = printer,
                    isSelected = state.selectedPrinter?.address == printer.address,
                    onClick = { onSavePrinter(printer) },
                )
            }
        }
    }
}

@Composable
private fun PrinterDeviceRow(
    printer: PrinterDevice,
    isSelected: Boolean,
    onClick: () -> Unit,
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        color = if (isSelected) {
            MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
        } else {
            MaterialTheme.colorScheme.surfaceContainerLowest
        },
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(
            1.dp,
            if (isSelected) MaterialTheme.colorScheme.primary else OutlineVariant.copy(alpha = 0.6f),
        ),
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = printer.name,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = printer.address,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text(
                text = if (isSelected) "Guardada" else "Guardar",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.primary,
                fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun ErrorState(
    message: String,
    onRetry: () -> Unit,
) {
    Card(
        shape = RoundedCornerShape(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer,
        ),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = message,
                color = MaterialTheme.colorScheme.onErrorContainer,
            )
            Button(
                onClick = onRetry,
                modifier = Modifier.padding(top = 12.dp),
            ) {
                Text("Reintentar")
            }
        }
    }
}

@Composable
private fun CatalogSummaryCard(
    title: String,
    count: Int,
    detail: String,
) {
    Card(
        shape = RoundedCornerShape(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceContainer,
        ),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                )
                Text(
                    text = detail,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text(
                text = count.toString(),
                style = MaterialTheme.typography.headlineSmall,
                color = MaterialTheme.colorScheme.primary,
            )
        }
    }
}

private data class CatalogRow(
    val title: String,
    val count: Int,
    val detail: String,
)

private fun requiredBluetoothPermissions(): List<String> =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        listOf(
            Manifest.permission.BLUETOOTH_CONNECT,
            Manifest.permission.BLUETOOTH_SCAN,
        )
    } else {
        emptyList()
    }

private fun catalogRows(bootstrap: BootstrapCatalogs): List<CatalogRow> = listOf(
    CatalogRow(
        title = "Monedas",
        count = bootstrap.currencies.size,
        detail = bootstrap.currencies.firstOrNull { it.isDefault }?.code ?: "Sin predeterminada",
    ),
    CatalogRow(
        title = "Impuestos",
        count = bootstrap.taxes.size,
        detail = bootstrap.taxes.firstOrNull { it.isDefault }?.name ?: "Sin predeterminado",
    ),
    CatalogRow(
        title = "Terminos de pago",
        count = bootstrap.paymentTerms.size,
        detail = bootstrap.paymentTerms.firstOrNull { it.isDefault }?.name ?: "Sin predeterminado",
    ),
    CatalogRow(
        title = "Garantias",
        count = bootstrap.warranties.size,
        detail = bootstrap.warranties.firstOrNull { it.isDefault }?.title ?: "Sin predeterminada",
    ),
    CatalogRow(
        title = "Cuentas bancarias",
        count = bootstrap.bankAccounts.size,
        detail = bootstrap.bankAccounts.firstOrNull { it.isDefault }?.name ?: "Sin predeterminada",
    ),
    CatalogRow(
        title = "Perfiles fiscales",
        count = bootstrap.fiscalProfiles.size,
        detail = bootstrap.fiscalProfiles.firstOrNull { it.isDefault }?.name ?: "Sin predeterminado",
    ),
    CatalogRow(
        title = "Textos legales",
        count = bootstrap.legalTexts.size,
        detail = bootstrap.legalTexts.firstOrNull { it.isDefault }?.name ?: "Sin predeterminado",
    ),
)
