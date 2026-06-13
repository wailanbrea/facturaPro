package com.facturador.facturapro.ui.verification

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.facturador.facturapro.domain.model.InvoiceVerification
import com.facturador.facturapro.domain.model.VerificationStatus
import com.facturador.facturapro.ui.common.SectionCard
import com.facturador.facturapro.ui.common.SectionTitle
import kotlinx.coroutines.launch

@Composable
fun VerificationScreen(
    state: VerificationUiState,
    onNumberChanged: (String) -> Unit,
    onCodeChanged: (String) -> Unit,
    onVerify: () -> Unit,
    onScanned: (ScannedCredentials) -> Unit,
    onScanFailed: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        SectionTitle(
            title = "Verificar documento",
            subtitle = "Comprueba si una factura o presupuesto es auténtico del sistema.",
        )

        Button(
            onClick = {
                scope.launch {
                    runCatching { scanVerificationQr(context) }
                        .onSuccess { raw ->
                            val parsed = parseVerificationPayload(raw)
                            when {
                                raw == null -> Unit // user cancelled
                                parsed == null -> onScanFailed("El código QR no corresponde a un documento de FacturaPro.")
                                else -> onScanned(parsed)
                            }
                        }
                        .onFailure { error ->
                            onScanFailed(error.message ?: "No se pudo abrir el escáner.")
                        }
                }
            },
            modifier = Modifier.fillMaxWidth(),
        ) {
            Icon(imageVector = Icons.Outlined.QrCodeScanner, contentDescription = null)
            Text(text = "  Escanear código QR")
        }

        SectionCard {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Text(
                    text = "O ingresa los datos manualmente",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                OutlinedTextField(
                    value = state.number,
                    onValueChange = onNumberChanged,
                    label = { Text("Número de documento") },
                    placeholder = { Text("FAC-000123") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    value = state.code,
                    onValueChange = onCodeChanged,
                    label = { Text("Código de seguridad") },
                    placeholder = { Text("A1B2-C3D4-E5F6-7890") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                Button(
                    onClick = onVerify,
                    enabled = !state.isLoading,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text("Verificar")
                }
            }
        }

        if (state.isLoading) {
            CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
        }

        state.errorMessage?.let { message ->
            ResultCard(
                color = MaterialTheme.colorScheme.errorContainer,
                contentColor = MaterialTheme.colorScheme.onErrorContainer,
                title = "Error",
                body = message,
            )
        }

        state.result?.let { ResultBlock(it) }
    }
}

@Composable
private fun ResultBlock(result: InvoiceVerification) {
    when (result.status) {
        VerificationStatus.AUTHENTIC -> {
            val invoice = result.invoice
            ResultCard(
                color = Color(0xFFDCFCE7),
                contentColor = Color(0xFF166534),
                icon = { Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Color(0xFF16A34A)) },
                title = "Documento auténtico",
                body = "Emitido por el sistema y sin alteraciones. Compara estos datos con el ejemplar:",
            ) {
                if (invoice != null) {
                    DataRow("Número", invoice.invoiceNumber)
                    DataRow("Emisor", listOfNotNull(invoice.sellerName, invoice.sellerTaxId).joinToString(" · "))
                    DataRow("Cliente", listOfNotNull(invoice.clientName, invoice.clientTaxId).joinToString(" · "))
                    DataRow("Fecha", invoice.invoiceDate)
                    DataRow("Total", listOfNotNull(invoice.total, invoice.currencyCode).joinToString(" "))
                }
            }
        }

        VerificationStatus.ALTERED -> ResultCard(
            color = MaterialTheme.colorScheme.errorContainer,
            contentColor = MaterialTheme.colorScheme.onErrorContainer,
            icon = { Icon(Icons.Filled.Error, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
            title = "Documento alterado",
            body = "Existe un registro con ese número y código, pero sus datos no coinciden con la firma original. No confíes en este documento.",
        )

        VerificationStatus.CODE_MISMATCH -> ResultCard(
            color = MaterialTheme.colorScheme.errorContainer,
            contentColor = MaterialTheme.colorScheme.onErrorContainer,
            icon = { Icon(Icons.Filled.Error, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
            title = "No auténtico",
            body = "El código de seguridad no corresponde al número indicado. Es una copia no auténtica o fue manipulado.",
        )

        VerificationStatus.NOT_FOUND -> ResultCard(
            color = MaterialTheme.colorScheme.errorContainer,
            contentColor = MaterialTheme.colorScheme.onErrorContainer,
            icon = { Icon(Icons.Filled.Error, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
            title = "No encontrado",
            body = "No existe ningún documento emitido con ese número. Es una copia no auténtica.",
        )

        VerificationStatus.UNKNOWN -> ResultCard(
            color = MaterialTheme.colorScheme.errorContainer,
            contentColor = MaterialTheme.colorScheme.onErrorContainer,
            title = "Resultado desconocido",
            body = "No se pudo interpretar la respuesta del servidor.",
        )
    }
}

@Composable
private fun ResultCard(
    color: Color,
    contentColor: Color,
    title: String,
    body: String,
    icon: (@Composable () -> Unit)? = null,
    extra: (@Composable () -> Unit)? = null,
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = color,
        contentColor = contentColor,
        shape = RoundedCornerShape(16.dp),
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            if (icon != null) icon()
            Text(text = title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text(text = body, style = MaterialTheme.typography.bodyMedium)
            if (extra != null) extra()
        }
    }
}

@Composable
private fun DataRow(label: String, value: String?) {
    if (value.isNullOrBlank()) return
    Text(
        text = "$label: $value",
        style = MaterialTheme.typography.bodyMedium,
        fontFamily = if (label == "Total" || label == "Número") FontFamily.Monospace else FontFamily.Default,
        fontWeight = if (label == "Total") FontWeight.Bold else FontWeight.Normal,
    )
}
