package com.facturador.facturapro.ui.home

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.ui.auth.LoginUiState

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    state: LoginUiState,
    onRetryBootstrap: () -> Unit,
    onLogout: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Scaffold(
        modifier = modifier.fillMaxSize(),
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("FacturaPro")
                        Text(
                            text = state.userName.orEmpty(),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                actions = {
                    OutlinedButton(onClick = onLogout) {
                        Text("Salir")
                    }
                },
            )
        },
    ) { padding ->
        HomeContent(
            state = state,
            onRetryBootstrap = onRetryBootstrap,
            modifier = Modifier.padding(padding),
        )
    }
}

@Composable
private fun HomeContent(
    state: LoginUiState,
    onRetryBootstrap: () -> Unit,
    modifier: Modifier = Modifier,
) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(20.dp),
        contentPadding = PaddingValues(bottom = 24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Text(
                text = "Configuracion inicial",
                style = MaterialTheme.typography.headlineSmall,
            )
            Text(
                text = "Catalogos cargados desde la API",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
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
            items(catalogRows(bootstrap)) { row ->
                CatalogSummaryCard(
                    title = row.title,
                    count = row.count,
                    detail = row.detail,
                )
            }
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
            Spacer(modifier = Modifier.height(12.dp))
            Button(onClick = onRetry) {
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
)
