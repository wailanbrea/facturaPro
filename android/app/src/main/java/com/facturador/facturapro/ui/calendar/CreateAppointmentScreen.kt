package com.facturador.facturapro.ui.calendar

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.facturador.facturapro.data.remote.dto.ContactDto
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.domain.model.AppointmentStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import org.json.JSONArray
import java.net.URL
import java.net.URLEncoder
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter

data class NominatimResult(
    val displayName: String,
    val lat: Double,
    val lon: Double,
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CreateAppointmentScreen(
    viewModel: CalendarViewModel,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current
    var title by remember { mutableStateOf("") }
    var locationText by remember { mutableStateOf("") }
    var locationSuggestions by remember { mutableStateOf<List<NominatimResult>>(emptyList()) }
    var selectedLocation by remember { mutableStateOf<NominatimResult?>(null) }
    var showSuggestions by remember { mutableStateOf(false) }
    var isSearching by remember { mutableStateOf(false) }
    var showMapSheet by remember { mutableStateOf(false) }
    var status by remember { mutableStateOf(AppointmentStatus.PENDING) }
    var startDate by remember { mutableStateOf(LocalDateTime.now().withMinute(0).plusHours(1)) }
    var endDate by remember { mutableStateOf(LocalDateTime.now().withMinute(0).plusHours(2)) }
    var observations by remember { mutableStateOf("") }
    var serviceDescription by remember { mutableStateOf("") }
    var isSubmitting by remember { mutableStateOf(false) }
    var errorMsg by remember { mutableStateOf<String?>(null) }

    val fmt = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm")

    // Debounced Nominatim search
    LaunchedEffect(locationText) {
        if (locationText.length < 3) { locationSuggestions = emptyList(); showSuggestions = false; return@LaunchedEffect }
        delay(350)
        isSearching = true
        try {
            val results = searchNominatim(locationText)
            locationSuggestions = results
            showSuggestions = results.isNotEmpty()
        } catch (_: Exception) {
        } finally {
            isSearching = false
        }
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.95f)
                .padding(horizontal = 20.dp)
                .padding(bottom = 24.dp),
        ) {
            // Header
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(bottom = 16.dp)) {
                Text("Nueva cita", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.weight(1f))
                IconButton(onClick = onDismiss) { Icon(Icons.Default.Close, contentDescription = "Cerrar") }
            }

            LazyColumn(modifier = Modifier.weight(1f)) {
                item {
                    OutlinedTextField(
                        value = title, onValueChange = { title = it },
                        label = { Text("Título *") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                        singleLine = true,
                    )
                }

                // Location with autocomplete
                item {
                    Text("Ubicación", fontWeight = FontWeight.Medium, fontSize = 13.sp, modifier = Modifier.padding(bottom = 4.dp))
                    OutlinedTextField(
                        value = locationText,
                        onValueChange = { locationText = it; selectedLocation = null },
                        placeholder = { Text("Escribe una dirección…", fontSize = 13.sp) },
                        leadingIcon = { Icon(Icons.Outlined.LocationOn, null, tint = Color(0xFFef4444)) },
                        trailingIcon = {
                            if (isSearching) CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp)
                            else if (locationText.isNotEmpty()) IconButton(onClick = { locationText = ""; selectedLocation = null; locationSuggestions = emptyList(); showSuggestions = false }) {
                                Icon(Icons.Default.Close, null, modifier = Modifier.size(18.dp))
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                    )

                    // Suggestions dropdown
                    if (showSuggestions) {
                        Card(
                            modifier = Modifier.fillMaxWidth().heightIn(max = 220.dp),
                            shape = RoundedCornerShape(8.dp),
                            elevation = CardDefaults.cardElevation(8.dp),
                        ) {
                            LazyColumn {
                                items(locationSuggestions) { result ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clickable {
                                                locationText = result.displayName
                                                selectedLocation = result
                                                showSuggestions = false
                                            }
                                            .padding(horizontal = 14.dp, vertical = 10.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                    ) {
                                        Icon(Icons.Outlined.Search, null, modifier = Modifier.size(16.dp), tint = MaterialTheme.colorScheme.primary)
                                        Spacer(Modifier.width(10.dp))
                                        Text(result.displayName, fontSize = 12.sp, maxLines = 2, modifier = Modifier.weight(1f))
                                    }
                                    HorizontalDivider(thickness = 0.5.dp)
                                }
                            }
                        }
                    }

                    // Map preview + nav buttons when location selected
                    if (selectedLocation != null) {
                        Spacer(Modifier.height(10.dp))
                        Card(shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth()) {
                            Column {
                                // Mini map placeholder with nav buttons
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(140.dp)
                                        .background(MaterialTheme.colorScheme.surfaceVariant),
                                    contentAlignment = Alignment.Center,
                                ) {
                                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                        Icon(Icons.Outlined.LocationOn, null, tint = Color(0xFFef4444), modifier = Modifier.size(32.dp))
                                        Text(selectedLocation!!.displayName, fontSize = 11.sp, maxLines = 2, modifier = Modifier.padding(horizontal = 12.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
                                    }
                                }
                                Row(Modifier.padding(10.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    val enc = URLEncoder.encode(selectedLocation!!.displayName, "UTF-8")
                                    NavChip("Google Maps", Color(0xFF4285F4)) {
                                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://www.google.com/maps/dir/?api=1&destination=$enc")))
                                    }
                                    NavChip("Waze", Color(0xFF33CCFF)) {
                                        val uri = Uri.parse("waze://?q=$enc&navigate=yes")
                                        val i = Intent(Intent.ACTION_VIEW, uri)
                                        if (i.resolveActivity(context.packageManager) != null) context.startActivity(i)
                                        else context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://waze.com/ul?q=$enc&navigate=yes")))
                                    }
                                }
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                    } else {
                        Spacer(Modifier.height(12.dp))
                    }
                }

                item {
                    OutlinedTextField(
                        value = serviceDescription, onValueChange = { serviceDescription = it },
                        label = { Text("Servicio a realizar") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                        minLines = 2,
                    )
                }

                item {
                    OutlinedTextField(
                        value = observations, onValueChange = { observations = it },
                        label = { Text("Observaciones") },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                        minLines = 2,
                    )
                }

                // Status selector
                item {
                    Text("Estado", fontWeight = FontWeight.Medium, fontSize = 13.sp, modifier = Modifier.padding(bottom = 6.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(bottom = 16.dp)) {
                        AppointmentStatus.entries
                            .filter { it != AppointmentStatus.CANCELLED }
                            .forEach { s ->
                                val color = runCatching { Color(android.graphics.Color.parseColor(s.colorHex)) }.getOrElse { MaterialTheme.colorScheme.primary }
                                FilterChip(
                                    selected = status == s,
                                    onClick = { status = s },
                                    label = { Text(s.label, fontSize = 11.sp) },
                                    colors = FilterChipDefaults.filterChipColors(
                                        selectedContainerColor = color.copy(alpha = 0.15f),
                                        selectedLabelColor = color,
                                    ),
                                )
                            }
                    }
                }

                item {
                    errorMsg?.let {
                        Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp, modifier = Modifier.padding(bottom = 8.dp))
                    }

                    Button(
                        onClick = {
                            if (title.isBlank()) { errorMsg = "El título es obligatorio"; return@Button }
                            isSubmitting = true
                            errorMsg = null
                            viewModel.createAppointment(
                                CreateAppointmentRequest(
                                    title = title,
                                    clientId = null,
                                    startAt = startDate.format(fmt),
                                    endAt = endDate.format(fmt),
                                    location = locationText.ifBlank { null },
                                    serviceDescription = serviceDescription.ifBlank { null },
                                    observations = observations.ifBlank { null },
                                    contacts = emptyList(),
                                    status = status.key,
                                ),
                                onSuccess = { isSubmitting = false; onDismiss() },
                                onError = { errorMsg = it; isSubmitting = false },
                            )
                        },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !isSubmitting,
                    ) {
                        if (isSubmitting) CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = Color.White)
                        else Text("Agendar cita")
                    }
                }
            }
        }
    }
}

@Composable
private fun NavChip(label: String, color: Color, onClick: () -> Unit) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(6.dp),
        color = color.copy(alpha = 0.1f),
        modifier = Modifier,
    ) {
        Row(Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.LocationOn, null, tint = color, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(4.dp))
            Text(label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = color)
        }
    }
}

private suspend fun searchNominatim(query: String): List<NominatimResult> = withContext(Dispatchers.IO) {
    val encoded = URLEncoder.encode(query, "UTF-8")
    val url = "https://nominatim.openstreetmap.org/search?format=json&limit=6&q=$encoded&accept-language=es"
    val conn = URL(url).openConnection()
    conn.setRequestProperty("User-Agent", "FacturaPro/1.0")
    conn.setRequestProperty("Accept-Language", "es")
    val json = conn.getInputStream().bufferedReader().readText()
    val arr = JSONArray(json)
    (0 until arr.length()).map { i ->
        val obj = arr.getJSONObject(i)
        NominatimResult(
            displayName = obj.getString("display_name"),
            lat = obj.getDouble("lat"),
            lon = obj.getDouble("lon"),
        )
    }
}
