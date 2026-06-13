package com.facturador.facturapro.ui.reports

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.WarningAmber
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.OperationalReport
import com.facturador.facturapro.domain.model.ReportOverdueInvoice
import com.facturador.facturapro.domain.model.ReportStatusRow
import com.facturador.facturapro.ui.common.EmptyState
import com.facturador.facturapro.ui.common.ErrorBanner
import com.facturador.facturapro.ui.common.InlineLoader
import com.facturador.facturapro.ui.common.IsoDatePickerField
import com.facturador.facturapro.ui.common.StatusBadge
import com.facturador.facturapro.ui.common.formatMoney
import com.facturador.facturapro.ui.common.invoiceStatusLabel
import com.facturador.facturapro.ui.common.statusColors
import com.facturador.facturapro.ui.theme.OutlineVariant
import java.math.BigDecimal
import java.math.RoundingMode

@Composable
fun ReportsScreen(
    state: ReportsUiState,
    bootstrap: BootstrapCatalogs?,
    onDateFromChanged: (String) -> Unit,
    onDateToChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onClearFilters: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val report = state.report

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            ReportsHeader(
                filtersLabel = activeRangeLabel(state),
                isLoading = state.isLoading,
                onRefresh = onRefresh,
            )
        }

        item {
            CompactFilters(
                state = state,
                bootstrap = bootstrap,
                onDateFromChanged = onDateFromChanged,
                onDateToChanged = onDateToChanged,
                onClearFilters = onClearFilters,
            )
        }

        if (state.isLoading) {
            item { InlineLoader() }
        }

        state.errorMessage?.let { message ->
            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    ErrorBanner(message = message)
                    Button(
                        onClick = onRefresh,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MaterialTheme.colorScheme.primary,
                            contentColor = MaterialTheme.colorScheme.onPrimary,
                        ),
                        shape = RoundedCornerShape(12.dp),
                    ) {
                        Text("Reintentar", fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }

        if (!state.isLoading && state.errorMessage == null && report == null) {
            item {
                EmptyState(
                    title = "Sin datos",
                    body = "No hay reportes disponibles para los filtros seleccionados.",
                )
            }
        }

        report?.let {
            item { KpiGrid(report = it) }
            item { StatusSummary(rows = it.byStatus) }
            item { RelevantInvoices(rows = it.overdueInvoices) }
        }
    }
}

@Composable
private fun ReportsHeader(
    filtersLabel: String,
    isLoading: Boolean,
    onRefresh: () -> Unit,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = "Reportes",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
            )
            Text(
                text = filtersLabel,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        IconButton(onClick = onRefresh, enabled = !isLoading) {
            Icon(
                imageVector = Icons.Outlined.Refresh,
                contentDescription = "Actualizar",
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun CompactFilters(
    state: ReportsUiState,
    bootstrap: BootstrapCatalogs?,
    onDateFromChanged: (String) -> Unit,
    onDateToChanged: (String) -> Unit,
    onClearFilters: () -> Unit,
) {
    val configuredCurrency = bootstrap?.currencies?.firstOrNull { it.isDefault }
        ?: bootstrap?.currencies?.firstOrNull()

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            IsoDatePickerField(
                label = "Desde",
                value = state.filters.dateFrom.orEmpty(),
                onDateSelected = onDateFromChanged,
                modifier = Modifier.weight(1f),
                enabled = !state.isLoading,
            )
            IsoDatePickerField(
                label = "Hasta",
                value = state.filters.dateTo.orEmpty(),
                onDateSelected = onDateToChanged,
                modifier = Modifier.weight(1f),
                enabled = !state.isLoading,
            )
        }
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            contentPadding = PaddingValues(end = 4.dp),
        ) {
            item {
                StaticInfoChip(
                    label = configuredCurrency?.let { "Moneda: ${it.code}" } ?: "Moneda configurada",
                )
            }
            item {
                ClearFilterChip(
                    enabled = !state.isLoading,
                    onClick = onClearFilters,
                )
            }
        }
    }
}

@Composable
private fun StaticInfoChip(
    label: String,
) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.7f)),
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 9.dp),
        )
    }
}

@Composable
private fun ClearFilterChip(
    enabled: Boolean,
    onClick: () -> Unit,
) {
    Surface(
        modifier = Modifier.clickable(enabled = enabled, onClick = onClick),
        color = Color.Transparent,
        shape = RoundedCornerShape(12.dp),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(
                imageVector = Icons.Outlined.Close,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(15.dp),
            )
            Spacer(Modifier.size(4.dp))
            Text(
                text = "Limpiar",
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
        }
    }
}

@Composable
private fun KpiGrid(report: OperationalReport) {
    val totals = report.totals ?: report.totalsByCurrency.firstOrNull()
    val invoiceCount = report.overview.invoicesCount
    val ticketAverage = totals?.let {
        averageAmount(it.totalInvoiced, invoiceCount)
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            KpiCard(
                label = "Total facturado",
                value = totals?.let { formatMoney(it.totalInvoiced, it.currencySymbol) } ?: "0,00",
                accent = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            KpiCard(
                label = "Total cobrado",
                value = totals?.let { formatMoney(it.totalCollected, it.currencySymbol) } ?: "0,00",
                accent = Color(0xFF047857),
                modifier = Modifier.weight(1f),
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            KpiCard(
                label = "Total pendiente",
                value = totals?.let { formatMoney(it.totalPending, it.currencySymbol) } ?: "0,00",
                accent = MaterialTheme.colorScheme.error,
                modifier = Modifier.weight(1f),
            )
            KpiCard(
                label = "Facturas vencidas",
                value = report.overview.overdueCount.toString(),
                accent = MaterialTheme.colorScheme.error,
                icon = Icons.Outlined.WarningAmber,
                modifier = Modifier.weight(1f),
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            KpiCard(
                label = "Cantidad facturas",
                value = invoiceCount.toString(),
                accent = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            KpiCard(
                label = "Ticket promedio",
                value = totals?.let { row ->
                    ticketAverage?.let { formatMoney(it, row.currencySymbol) }
                } ?: "0,00",
                accent = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun KpiCard(
    label: String,
    value: String,
    accent: Color,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
) {
    Surface(
        modifier = modifier.height(96.dp),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.65f)),
        shadowElevation = 1.dp,
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = value,
                    fontSize = 19.sp,
                    lineHeight = 24.sp,
                    fontWeight = FontWeight.Bold,
                    color = accent,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                icon?.let {
                    Spacer(Modifier.size(6.dp))
                    Icon(
                        imageVector = it,
                        contentDescription = null,
                        tint = accent,
                        modifier = Modifier.size(19.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun StatusSummary(rows: List<ReportStatusRow>) {
    val summaries = remember(rows) { statusSummaries(rows) }

    ReportBlock(
        title = "Resumen por estado",
        isEmpty = summaries.isEmpty(),
        emptyText = "Sin estados para mostrar.",
    ) {
        LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            items(summaries, key = { it.status }) { summary ->
                StatusSummaryCard(summary = summary)
            }
        }
    }
}

@Composable
private fun StatusSummaryCard(summary: StatusSummary) {
    val (bg, fg) = statusColors(summary.status)
    Surface(
        modifier = Modifier.size(width = 128.dp, height = 78.dp),
        color = bg.copy(alpha = 0.55f),
        shape = RoundedCornerShape(10.dp),
        border = BorderStroke(1.dp, fg.copy(alpha = 0.18f)),
    ) {
        Column(
            modifier = Modifier.padding(10.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Text(
                text = shortStatusLabel(summary.status).uppercase(),
                style = MaterialTheme.typography.labelSmall,
                fontWeight = FontWeight.Bold,
                color = fg,
                textAlign = TextAlign.Center,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = summary.count.toString(),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = fg,
            )
        }
    }
}

@Composable
private fun RelevantInvoices(rows: List<ReportOverdueInvoice>) {
    ReportBlock(
        title = "Facturas relevantes",
        icon = Icons.Outlined.Description,
        isEmpty = rows.isEmpty(),
        emptyText = "No hay facturas vencidas en el periodo.",
    ) {
        rows.take(6).forEachIndexed { index, invoice ->
            InvoiceMovementRow(invoice = invoice)
            if (index < rows.take(6).lastIndex) {
                HorizontalDivider(color = OutlineVariant.copy(alpha = 0.35f))
            }
        }
    }
}

@Composable
private fun InvoiceMovementRow(invoice: ReportOverdueInvoice) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = invoice.invoiceNumber ?: "Borrador #${invoice.id}",
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = invoice.clientName,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = invoice.dueDate?.let { "Vence $it" } ?: "Sin vencimiento",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(
                text = formatMoney(invoice.total, invoice.currencySymbol),
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Text(
                text = "Pend. ${formatMoney(invoice.balanceDue, invoice.currencySymbol)}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.error,
            )
            Spacer(Modifier.size(4.dp))
            StatusBadge(status = invoice.status, dense = true)
        }
    }
}

@Composable
private fun ReportBlock(
    title: String,
    isEmpty: Boolean,
    emptyText: String,
    icon: ImageVector? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 2.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = title.uppercase(),
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.weight(1f),
            )
            icon?.let {
                Icon(
                    imageVector = it,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(19.dp),
                )
            }
        }
        Surface(
            modifier = Modifier.fillMaxWidth(),
            color = MaterialTheme.colorScheme.surfaceContainerLowest,
            shape = RoundedCornerShape(14.dp),
            border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.65f)),
            shadowElevation = 1.dp,
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                if (isEmpty) {
                    Text(
                        text = emptyText,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                } else {
                    content()
                }
            }
        }
    }
}

private data class StatusSummary(
    val status: String,
    val count: Int,
)

private fun statusSummaries(rows: List<ReportStatusRow>): List<StatusSummary> {
    val order = listOf("issued", "paid", "partially_paid", "overdue", "cancelled")
    val grouped = rows
        .groupBy { it.status }
        .mapValues { entry -> entry.value.sumOf { it.invoicesCount } }

    return order.mapNotNull { status ->
        grouped[status]?.let { StatusSummary(status = status, count = it) }
    } + grouped
        .filterKeys { it !in order }
        .map { StatusSummary(status = it.key, count = it.value) }
}

private fun shortStatusLabel(status: String): String = when (status) {
    "partially_paid" -> "Parciales"
    else -> invoiceStatusLabel(status)
}

private fun activeRangeLabel(state: ReportsUiState): String {
    val from = state.filters.dateFrom
    val to = state.filters.dateTo

    return when {
        from != null && to != null -> "$from - $to"
        from != null -> "Desde $from"
        to != null -> "Hasta $to"
        else -> "Periodo completo"
    }
}

private fun averageAmount(total: String, count: Int): String? {
    if (count <= 0) return null

    return runCatching {
        total.toBigDecimal()
            .divide(BigDecimal(count), 4, RoundingMode.HALF_UP)
            .toPlainString()
    }.getOrNull()
}
