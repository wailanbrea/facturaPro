package com.facturador.facturapro.ui.dashboard

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.TrendingUp
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Paid
import androidx.compose.material.icons.outlined.WarningAmber
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.facturador.facturapro.domain.model.DashboardSummary
import com.facturador.facturapro.domain.model.MonthlyPoint
import com.facturador.facturapro.domain.model.RecentInvoice
import com.facturador.facturapro.ui.theme.OutlineVariant
import com.facturador.facturapro.ui.theme.StatusDraftBg
import com.facturador.facturapro.ui.theme.StatusDraftFg
import com.facturador.facturapro.ui.theme.StatusIssuedBg
import com.facturador.facturapro.ui.theme.StatusIssuedFg
import com.facturador.facturapro.ui.theme.StatusOverdueBg
import com.facturador.facturapro.ui.theme.StatusOverdueFg
import com.facturador.facturapro.ui.theme.StatusPaidBg
import com.facturador.facturapro.ui.theme.StatusPaidFg
import com.facturador.facturapro.ui.theme.StatusPendingBg
import com.facturador.facturapro.ui.theme.StatusPendingFg
import java.math.BigDecimal
import java.math.RoundingMode

@Composable
fun DashboardScreen(
    userName: String?,
    state: DashboardUiState,
    onNewInvoice: () -> Unit,
    onSeeAllInvoices: () -> Unit,
    onOpenInvoice: (Long) -> Unit,
    onRefresh: () -> Unit = {},
    modifier: Modifier = Modifier,
) {
    val summary = state.summary

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(20.dp),
    ) {
        item {
            Column {
                Text(
                    text = greetingFor(userName),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    text = "Resumen financiero",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
            }
        }

        if (summary == null) {
            item {
                when {
                    state.isLoading -> Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 48.dp),
                        contentAlignment = Alignment.Center,
                    ) { CircularProgressIndicator() }

                    state.errorMessage != null -> ErrorCard(message = state.errorMessage, onRetry = onRefresh)
                }
            }
            return@LazyColumn
        }

        val symbol = summary.currencySymbol

        item {
            KpiHeroCard(
                title = "Total facturado",
                amount = symbol + formatAmount(summary.totalBilled),
                trend = "${summary.invoiceCount} facturas · ${summary.clientCount} clientes",
                icon = Icons.AutoMirrored.Outlined.TrendingUp,
            )
        }

        item {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                KpiCard(
                    modifier = Modifier.weight(1f),
                    title = "Facturado mes",
                    value = symbol + formatAmount(summary.totalBilledMonth),
                    detail = trendLabel(summary.monthlyTrend),
                    icon = Icons.AutoMirrored.Outlined.TrendingUp,
                    valueColor = MaterialTheme.colorScheme.onSurface,
                    detailColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    iconBg = MaterialTheme.colorScheme.surfaceContainerLow,
                    iconFg = MaterialTheme.colorScheme.primary,
                )
                KpiCard(
                    modifier = Modifier.weight(1f),
                    title = "Cobrado",
                    value = symbol + formatAmount(summary.totalCollected),
                    detail = "${formatPercent(summary.collectionRate)} cobrado",
                    icon = Icons.Outlined.Paid,
                    valueColor = StatusPaidFg,
                    detailColor = StatusPaidFg,
                    iconBg = StatusPaidBg,
                    iconFg = StatusPaidFg,
                    borderColor = StatusPaidBg,
                )
            }
        }

        item {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                KpiCard(
                    modifier = Modifier.weight(1f),
                    title = "Pendiente",
                    value = symbol + formatAmount(summary.pendingBalance),
                    detail = "${summary.pendingCount} por cobrar",
                    icon = Icons.Outlined.WarningAmber,
                    valueColor = StatusPendingFg,
                    detailColor = StatusPendingFg,
                    iconBg = StatusPendingBg,
                    iconFg = StatusPendingFg,
                    borderColor = StatusPendingBg,
                )
                KpiCard(
                    modifier = Modifier.weight(1f),
                    title = "Vencidas",
                    value = summary.overdueCount.toString(),
                    detail = if (summary.overdueCount == 0) "Sin vencimientos" else "Requieren atención",
                    icon = Icons.Outlined.WarningAmber,
                    valueColor = StatusOverdueFg,
                    detailColor = StatusOverdueFg,
                    iconBg = StatusOverdueBg,
                    iconFg = StatusOverdueFg,
                    borderColor = StatusOverdueBg,
                )
            }
        }

        item {
            MonthlyChartCard(series = summary.monthlySeries, symbol = symbol)
        }

        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "Actividad reciente",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = "Ver todo",
                    style = MaterialTheme.typography.labelLarge,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier
                        .clickable(onClick = onSeeAllInvoices)
                        .padding(vertical = 4.dp, horizontal = 6.dp),
                )
            }
        }

        if (summary.recentInvoices.isEmpty()) {
            item { EmptyActivityCard() }
        } else {
            item {
                Surface(
                    color = MaterialTheme.colorScheme.surfaceContainerLowest,
                    shape = RoundedCornerShape(16.dp),
                    border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
                    shadowElevation = 1.dp,
                ) {
                    Column {
                        summary.recentInvoices.forEachIndexed { index, invoice ->
                            ActivityRow(invoice = invoice, onClick = { onOpenInvoice(invoice.id) })
                            if (index != summary.recentInvoices.lastIndex) {
                                androidx.compose.material3.HorizontalDivider(
                                    color = OutlineVariant.copy(alpha = 0.4f),
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun KpiHeroCard(
    title: String,
    amount: String,
    trend: String,
    icon: ImageVector,
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
        shadowElevation = 1.dp,
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = title.uppercase(),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.SemiBold,
                )
                IconBadge(
                    icon = icon,
                    bg = MaterialTheme.colorScheme.primary.copy(alpha = 0.1f),
                    fg = MaterialTheme.colorScheme.primary,
                )
            }
            Spacer(Modifier.height(8.dp))
            Text(
                text = amount,
                fontSize = 30.sp,
                lineHeight = 38.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.CheckCircle,
                    contentDescription = null,
                    tint = StatusPaidFg,
                    modifier = Modifier.size(14.dp),
                )
                Spacer(Modifier.width(4.dp))
                Text(
                    text = trend,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun KpiCard(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    detail: String,
    icon: ImageVector,
    valueColor: Color,
    detailColor: Color,
    iconBg: Color,
    iconFg: Color,
    borderColor: Color = OutlineVariant.copy(alpha = 0.6f),
) {
    Surface(
        modifier = modifier,
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, borderColor),
        shadowElevation = 1.dp,
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = title.uppercase(),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.SemiBold,
                )
                IconBadge(icon = icon, bg = iconBg, fg = iconFg, small = true)
            }
            Spacer(Modifier.height(6.dp))
            Text(
                text = value,
                fontSize = 22.sp,
                lineHeight = 28.sp,
                fontWeight = FontWeight.Bold,
                color = valueColor,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = detail,
                style = MaterialTheme.typography.labelMedium,
                color = detailColor,
            )
        }
    }
}

@Composable
private fun MonthlyChartCard(series: List<MonthlyPoint>, symbol: String) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
        shadowElevation = 1.dp,
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Text(
                text = "Facturación (últimos 6 meses)",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.height(20.dp))
            val maxValue = (series.maxOfOrNull { it.value } ?: 0.0).coerceAtLeast(1.0)
            val lastIndex = series.lastIndex
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(140.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.Bottom,
            ) {
                series.forEachIndexed { index, bar ->
                    val fraction = if (bar.value <= 0.0) 0f else (bar.value / maxValue).toFloat().coerceIn(0.08f, 1f)
                    Column(
                        modifier = Modifier.weight(1f),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height((110 * fraction).dp)
                                .background(
                                    color = if (index == lastIndex) MaterialTheme.colorScheme.primary
                                    else MaterialTheme.colorScheme.surfaceContainerHighest,
                                    shape = RoundedCornerShape(topStart = 6.dp, topEnd = 6.dp),
                                ),
                        )
                        Spacer(Modifier.height(8.dp))
                        Text(
                            text = bar.label,
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ActivityRow(
    invoice: RecentInvoice,
    onClick: () -> Unit,
) {
    val (statusBg, statusFg) = statusColors(invoice.status)
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .background(statusBg.copy(alpha = 0.55f), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                imageVector = Icons.Outlined.Description,
                contentDescription = null,
                tint = statusFg,
                modifier = Modifier.size(20.dp),
            )
        }
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = invoice.clientName,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Text(
                text = (invoice.invoiceNumber ?: "Borrador") + " • " + invoice.invoiceDate,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(
                text = invoice.currencySymbol + formatAmount(invoice.total),
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.height(4.dp))
            StatusPill(label = statusLabel(invoice.status), bg = statusBg, fg = statusFg)
        }
    }
}

@Composable
private fun ErrorCard(message: String, onRetry: () -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.errorContainer,
        contentColor = MaterialTheme.colorScheme.onErrorContainer,
        shape = RoundedCornerShape(16.dp),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(text = message, style = MaterialTheme.typography.bodyMedium)
            Button(onClick = onRetry, modifier = Modifier.padding(top = 12.dp)) {
                Text("Reintentar")
            }
        }
    }
}

@Composable
private fun EmptyActivityCard() {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                text = "Aún no hay facturas registradas.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun StatusPill(label: String, bg: Color, fg: Color) {
    Surface(color = bg, shape = RoundedCornerShape(50)) {
        Text(
            text = label.uppercase(),
            style = MaterialTheme.typography.labelSmall,
            color = fg,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 3.dp),
        )
    }
}

@Composable
private fun IconBadge(
    icon: ImageVector,
    bg: Color,
    fg: Color,
    small: Boolean = false,
) {
    val sz = if (small) 32.dp else 40.dp
    val ic = if (small) 16.dp else 20.dp
    Box(
        modifier = Modifier
            .size(sz)
            .background(bg, RoundedCornerShape(10.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(imageVector = icon, contentDescription = null, tint = fg, modifier = Modifier.size(ic))
    }
}

private fun greetingFor(name: String?): String {
    val first = name?.trim()?.split(" ")?.firstOrNull()?.replaceFirstChar { it.uppercase() }
    return if (first.isNullOrBlank()) "Bienvenido de nuevo" else "Hola, $first"
}

private fun trendLabel(trend: Double?): String = when {
    trend == null -> "vs. mes anterior"
    trend >= 0 -> "+${formatPercent(trend)} vs. mes anterior"
    else -> "${formatPercent(trend)} vs. mes anterior"
}

private fun formatPercent(value: Double): String =
    BigDecimal(value).setScale(1, RoundingMode.HALF_UP).toPlainString() + "%"

private fun formatAmount(raw: String): String {
    val bd = runCatching { BigDecimal(raw) }.getOrDefault(BigDecimal.ZERO)
        .setScale(2, RoundingMode.HALF_UP)
    val parts = bd.toPlainString().split(".")
    val integer = parts[0].replace("-", "").reversed().chunked(3).joinToString(",").reversed()
    val sign = if (bd.signum() < 0) "-" else ""
    val decimals = parts.getOrNull(1) ?: "00"
    return "$sign$integer.$decimals"
}

private fun statusLabel(status: String): String = when (status) {
    "draft" -> "Borrador"
    "issued" -> "Emitida"
    "accepted" -> "Aceptado"
    "converted" -> "Convertido"
    "paid" -> "Pagada"
    "partially_paid" -> "Parcial"
    "overdue" -> "Vencida"
    "cancelled" -> "Anulada"
    else -> status
}

private fun statusColors(status: String): Pair<Color, Color> = when (status) {
    "paid" -> StatusPaidBg to StatusPaidFg
    "issued" -> StatusIssuedBg to StatusIssuedFg
    "accepted" -> StatusIssuedBg to StatusIssuedFg
    "converted" -> StatusIssuedBg to StatusIssuedFg
    "partially_paid" -> StatusPendingBg to StatusPendingFg
    "overdue" -> StatusOverdueBg to StatusOverdueFg
    "cancelled" -> StatusOverdueBg to StatusOverdueFg
    else -> StatusDraftBg to StatusDraftFg
}
