package com.facturador.facturapro.ui.common

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Inbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
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
import androidx.compose.ui.unit.dp
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

/** Pill-shaped status badge used across invoices/clients screens. */
@Composable
fun StatusBadge(
    status: String,
    modifier: Modifier = Modifier,
    dense: Boolean = false,
    label: String = invoiceStatusLabel(status),
) {
    val (bg, fg) = statusColors(status)
    Surface(color = bg, shape = RoundedCornerShape(50), modifier = modifier) {
        Text(
            text = label.uppercase(),
            style = MaterialTheme.typography.labelSmall,
            color = fg,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(
                horizontal = if (dense) 8.dp else 10.dp,
                vertical = if (dense) 2.dp else 3.dp,
            ),
        )
    }
}

/** Circular avatar that derives its color from the initials hash. */
@Composable
fun InitialAvatar(
    text: String,
    modifier: Modifier = Modifier,
    size: androidx.compose.ui.unit.Dp = 44.dp,
) {
    val initials = remember(text) { initialsFor(text) }
    val palette = avatarColorFor(text)
    Box(
        modifier = modifier
            .size(size)
            .background(palette.background, RoundedCornerShape(12.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = initials,
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.Bold,
            color = palette.foreground,
        )
    }
}

/** Generic outlined section card used as a building block. */
@Composable
fun SectionCard(
    modifier: Modifier = Modifier,
    content: @Composable () -> Unit,
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
        shadowElevation = 1.dp,
        content = content,
    )
}

/** Title + optional subtitle row used in section headers. */
@Composable
fun SectionTitle(
    title: String,
    subtitle: String? = null,
    trailing: (@Composable () -> Unit)? = null,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            if (!subtitle.isNullOrBlank()) {
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        if (trailing != null) trailing()
    }
}

@Composable
fun EmptyState(
    icon: ImageVector = Icons.Outlined.Inbox,
    title: String,
    body: String,
    modifier: Modifier = Modifier,
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(28.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Box(
                modifier = Modifier
                    .size(54.dp)
                    .background(
                        MaterialTheme.colorScheme.surfaceContainer,
                        RoundedCornerShape(14.dp),
                    ),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(28.dp),
                )
            }
            Spacer(Modifier.size(14.dp))
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.size(4.dp))
            Text(
                text = body,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            )
        }
    }
}

@Composable
fun InlineLoader(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(vertical = 24.dp),
        horizontalArrangement = Arrangement.Center,
    ) {
        CircularProgressIndicator(
            strokeWidth = 2.5.dp,
            color = MaterialTheme.colorScheme.primary,
        )
    }
}

@Composable
fun ErrorBanner(message: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.errorContainer,
        shape = RoundedCornerShape(12.dp),
    ) {
        Text(
            text = message,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onErrorContainer,
            modifier = Modifier.padding(14.dp),
        )
    }
}

/** Lookup-only helpers so callers don't need to import theme colors. */
fun statusColors(status: String): Pair<Color, Color> = when (status) {
    "paid" -> StatusPaidBg to StatusPaidFg
    "issued" -> StatusIssuedBg to StatusIssuedFg
    "accepted" -> StatusIssuedBg to StatusIssuedFg
    "converted" -> StatusIssuedBg to StatusIssuedFg
    "partially_paid" -> StatusPendingBg to StatusPendingFg
    "overdue" -> StatusOverdueBg to StatusOverdueFg
    "cancelled" -> StatusOverdueBg to StatusOverdueFg
    else -> StatusDraftBg to StatusDraftFg
}

fun invoiceStatusLabel(status: String): String = when (status) {
    "draft" -> "Borrador"
    "issued" -> "Emitida"
    "accepted" -> "Aceptado"
    "converted" -> "Convertido"
    "partially_paid" -> "Parcial"
    "paid" -> "Pagada"
    "overdue" -> "Vencida"
    "cancelled" -> "Anulada"
    else -> status.replace('_', ' ').replaceFirstChar { it.uppercase() }
}

/** US-style thousands separator + 2 decimals; safe against unparseable strings. */
fun formatMoney(amount: String, symbol: String? = null): String {
    val decimal = amount.toBigDecimalOrZero().setScale(2, RoundingMode.HALF_UP)
    val parts = decimal.toPlainString().split(".")
    val integer = parts[0].toLong()
        .let { java.text.NumberFormat.getIntegerInstance(java.util.Locale.US).format(it) }
    val decimals = parts.getOrNull(1) ?: "00"
    val core = "$integer.$decimals"
    return if (symbol.isNullOrBlank()) core else "$symbol $core"
}

fun String.toBigDecimalOrZero(): BigDecimal = runCatching {
    trim().ifBlank { "0" }.toBigDecimal()
}.getOrDefault(BigDecimal.ZERO)

/** Returns first 2 capitalised initials from a name; falls back to a single letter. */
fun initialsFor(name: String): String {
    val tokens = name.trim().split(Regex("\\s+")).filter { it.isNotBlank() }
    return when {
        tokens.isEmpty() -> "?"
        tokens.size == 1 -> tokens[0].take(2).uppercase()
        else -> (tokens[0].first().toString() + tokens[1].first().toString()).uppercase()
    }
}

data class AvatarPalette(val background: Color, val foreground: Color)

private val avatarPalettes = listOf(
    AvatarPalette(Color(0xFFDCE1FF), Color(0xFF0037B0)), // primary tint
    AvatarPalette(Color(0xFFD1FAE5), Color(0xFF047857)), // emerald
    AvatarPalette(Color(0xFFFEE2E2), Color(0xFFB42318)), // rose
    AvatarPalette(Color(0xFFFEF3C7), Color(0xFFB45309)), // amber
    AvatarPalette(Color(0xFFE0E7FF), Color(0xFF4338CA)), // indigo
    AvatarPalette(Color(0xFFCFFAFE), Color(0xFF0E7490)), // cyan
    AvatarPalette(Color(0xFFFCE7F3), Color(0xFF9D174D)), // pink
    AvatarPalette(Color(0xFFF3E8FF), Color(0xFF6B21A8)), // purple
)

fun avatarColorFor(seed: String): AvatarPalette {
    if (seed.isBlank()) return avatarPalettes[0]
    val hash = seed.sumOf { it.code }
    return avatarPalettes[Math.floorMod(hash, avatarPalettes.size)]
}
