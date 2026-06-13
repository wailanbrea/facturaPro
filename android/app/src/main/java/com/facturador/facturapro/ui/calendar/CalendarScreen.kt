package com.facturador.facturapro.ui.calendar

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Navigation
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.facturador.facturapro.domain.model.Appointment
import com.facturador.facturapro.domain.model.AppointmentStatus
import java.net.URLEncoder
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.YearMonth
import java.time.format.TextStyle
import java.util.Locale

@Composable
fun CalendarScreen(viewModel: CalendarViewModel) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    var showCreate by remember { mutableStateOf(false) }

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.weight(1f)) {
                CalendarHeader(
                    yearMonth = state.yearMonth,
                    onPrev = viewModel::previousMonth,
                    onNext = viewModel::nextMonth,
                )
            }
            FloatingActionButton(
                onClick = { showCreate = true },
                modifier = Modifier.size(40.dp),
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary,
            ) {
                Icon(Icons.Default.Add, contentDescription = "Nueva cita", modifier = Modifier.size(20.dp))
            }
        }
        Spacer(Modifier.height(8.dp))
        WeekDayRow()
        Spacer(Modifier.height(4.dp))

        if (state.isLoading) {
            Box(Modifier.fillMaxWidth().weight(1f), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        } else {
            CalendarGrid(
                yearMonth = state.yearMonth,
                appointments = state.appointments,
                selectedDate = state.selectedDate,
                onDayClick = { viewModel.selectDate(it) },
            )
        }

        state.selectedDate?.let { date ->
            val dayAppts = state.appointments.filter { it.startAt.startsWith(date) }
            if (dayAppts.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Text("Citas del día", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                Spacer(Modifier.height(6.dp))
                LazyColumn(modifier = Modifier.weight(1f)) {
                    items(dayAppts) { appt ->
                        AppointmentCard(appt)
                    }
                }
            }
        }

        state.error?.let {
            Spacer(Modifier.height(8.dp))
            Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp)
        }
    }

    if (showCreate) {
        CreateAppointmentScreen(
            viewModel = viewModel,
            onDismiss = { showCreate = false },
        )
    }
}

@Composable
private fun CalendarHeader(
    yearMonth: YearMonth,
    onPrev: () -> Unit,
    onNext: () -> Unit,
) {
    val monthName = yearMonth.month.getDisplayName(TextStyle.FULL_STANDALONE, Locale("es"))
        .replaceFirstChar { it.uppercase() }

    Row(verticalAlignment = Alignment.CenterVertically) {
        IconButton(onClick = onPrev) {
            Icon(Icons.Default.ChevronLeft, contentDescription = "Mes anterior")
        }
        Text(
            text = "$monthName ${yearMonth.year}",
            fontWeight = FontWeight.Bold,
            fontSize = 18.sp,
            modifier = Modifier.weight(1f),
            textAlign = TextAlign.Center,
        )
        IconButton(onClick = onNext) {
            Icon(Icons.Default.ChevronRight, contentDescription = "Mes siguiente")
        }
    }
}

@Composable
private fun WeekDayRow() {
    val days = listOf("Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom")
    Row(Modifier.fillMaxWidth()) {
        days.forEach { day ->
            Text(
                text = day,
                modifier = Modifier.weight(1f),
                textAlign = TextAlign.Center,
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun CalendarGrid(
    yearMonth: YearMonth,
    appointments: List<Appointment>,
    selectedDate: String?,
    onDayClick: (String) -> Unit,
) {
    val today = LocalDate.now()
    val firstDay = yearMonth.atDay(1)
    val startOffset = (firstDay.dayOfWeek.value - DayOfWeek.MONDAY.value + 7) % 7
    val daysInMonth = yearMonth.lengthOfMonth()
    val byDay = appointments.groupBy { it.startAt.substring(0, 10) }

    val cells = buildList {
        repeat(startOffset) { add(null) }
        for (d in 1..daysInMonth) add(yearMonth.atDay(d))
        val trailing = (7 - size % 7) % 7
        repeat(trailing) { add(null) }
    }

    Column {
        cells.chunked(7).forEach { week ->
            Row(Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
                week.forEach { day ->
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .aspectRatio(0.9f)
                            .padding(1.dp)
                            .clip(RoundedCornerShape(6.dp))
                            .background(
                                when {
                                    day == null -> Color.Transparent
                                    day == today -> MaterialTheme.colorScheme.primaryContainer
                                    day.toString() == selectedDate -> MaterialTheme.colorScheme.secondaryContainer
                                    else -> MaterialTheme.colorScheme.surface
                                }
                            )
                            .then(if (day != null) Modifier.clickable { onDayClick(day.toString()) } else Modifier),
                    ) {
                        if (day != null) {
                            Column(Modifier.padding(3.dp)) {
                                Text(
                                    text = day.dayOfMonth.toString(),
                                    fontSize = 11.sp,
                                    fontWeight = if (day == today) FontWeight.Bold else FontWeight.Normal,
                                    color = if (day == today) MaterialTheme.colorScheme.primary
                                    else MaterialTheme.colorScheme.onSurface,
                                )
                                val dayAppts = byDay[day.toString()]?.take(2) ?: emptyList()
                                dayAppts.forEach { appt ->
                                    val color = runCatching {
                                        Color(android.graphics.Color.parseColor(appt.status.colorHex))
                                    }.getOrElse { MaterialTheme.colorScheme.primary }
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(3.dp))
                                            .background(color)
                                            .padding(horizontal = 2.dp, vertical = 1.dp),
                                    ) {
                                        Text(
                                            appt.title,
                                            fontSize = 8.sp,
                                            color = Color.White,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis,
                                        )
                                    }
                                }
                                val extra = (byDay[day.toString()]?.size ?: 0) - 2
                                if (extra > 0) {
                                    Text("+$extra", fontSize = 8.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun AppointmentCard(appointment: Appointment) {
    val context = LocalContext.current
    var showNavSheet by remember { mutableStateOf(false) }

    val color = runCatching {
        Color(android.graphics.Color.parseColor(appointment.status.colorHex))
    }.getOrElse { MaterialTheme.colorScheme.primary }

    Card(
        modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp),
        shape = RoundedCornerShape(8.dp),
    ) {
        Column(Modifier.padding(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(10.dp)
                        .clip(CircleShape)
                        .background(color),
                )
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(appointment.title, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text(
                        buildString {
                            append(appointment.startAt.substring(11, 16))
                            if (appointment.clientName != null) append(" · ${appointment.clientName}")
                        },
                        fontSize = 11.sp,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Badge(containerColor = color) {
                    Text(appointment.status.label, fontSize = 9.sp, color = Color.White)
                }
            }

            // Location row — tappable
            if (!appointment.location.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(6.dp))
                        .clickable { showNavSheet = true }
                        .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(Icons.Outlined.LocationOn, contentDescription = null, modifier = Modifier.size(14.dp), tint = Color(0xFFef4444))
                    Spacer(Modifier.width(5.dp))
                    Text(appointment.location, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                    Icon(Icons.Outlined.Navigation, contentDescription = "Navegar", modifier = Modifier.size(13.dp), tint = MaterialTheme.colorScheme.primary)
                }
            }
        }
    }

    // Navigation bottom sheet
    if (showNavSheet && !appointment.location.isNullOrBlank()) {
        NavigationSheet(
            location = appointment.location,
            context = context,
            onDismiss = { showNavSheet = false },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NavigationSheet(
    location: String,
    context: Context,
    onDismiss: () -> Unit,
) {
    val encoded = URLEncoder.encode(location, "UTF-8")

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 32.dp)) {
            Text("Navegar a", fontWeight = FontWeight.Bold, fontSize = 16.sp)
            Spacer(Modifier.height(4.dp))
            Text(location, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 2)
            Spacer(Modifier.height(16.dp))

            NavOption(
                label = "Google Maps",
                subtitle = "Abrir ruta en Google Maps",
                color = Color(0xFF4285F4),
                onClick = {
                    openUri(context, "https://www.google.com/maps/dir/?api=1&destination=$encoded")
                    onDismiss()
                },
            )
            Spacer(Modifier.height(8.dp))
            NavOption(
                label = "Waze",
                subtitle = "Navegar con Waze",
                color = Color(0xFF33CCFF),
                onClick = {
                    // Try Waze deep link first, fallback to web
                    val wazeUri = Uri.parse("waze://?q=$encoded&navigate=yes")
                    val intent = Intent(Intent.ACTION_VIEW, wazeUri)
                    if (intent.resolveActivity(context.packageManager) != null) {
                        context.startActivity(intent)
                    } else {
                        openUri(context, "https://waze.com/ul?q=$encoded&navigate=yes")
                    }
                    onDismiss()
                },
            )
            Spacer(Modifier.height(8.dp))
            NavOption(
                label = "Abrir en mapa del sistema",
                subtitle = "Usar la app de mapas predeterminada",
                color = Color(0xFF6b7280),
                onClick = {
                    val geoUri = Uri.parse("geo:0,0?q=$encoded")
                    context.startActivity(Intent(Intent.ACTION_VIEW, geoUri))
                    onDismiss()
                },
            )
        }
    }
}

@Composable
private fun NavOption(
    label: String,
    subtitle: String,
    color: Color,
    onClick: () -> Unit,
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(10.dp),
        color = color.copy(alpha = 0.08f),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(36.dp).clip(CircleShape).background(color), contentAlignment = Alignment.Center) {
                Icon(Icons.Outlined.Navigation, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column {
                Text(label, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = color)
                Text(subtitle, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

private fun openUri(context: Context, url: String) {
    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
}
