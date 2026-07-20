package com.facturador.facturapro.ui.calendar

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.webkit.WebViewClient
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Add
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
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.remote.dto.ContactDto
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.domain.model.Appointment
import com.facturador.facturapro.domain.model.AppointmentStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
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
    existingAppointment: Appointment? = null,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    
    var title by remember { mutableStateOf(existingAppointment?.title ?: "") }
    var locationText by remember { mutableStateOf(existingAppointment?.location ?: "") }
    var locationLat by remember { mutableStateOf(existingAppointment?.locationLat) }
    var locationLng by remember { mutableStateOf(existingAppointment?.locationLng) }
    var locationSuggestions by remember { mutableStateOf<List<NominatimResult>>(emptyList()) }
    var selectedLocation by remember { mutableStateOf<NominatimResult?>(null) }
    var showSuggestions by remember { mutableStateOf(false) }
    var isSearching by remember { mutableStateOf(false) }
    var status by remember { mutableStateOf(existingAppointment?.status ?: AppointmentStatus.PENDING) }
    
    var startDate by remember { mutableStateOf(parseDateTime(existingAppointment?.startAt)) }
    var endDate by remember { mutableStateOf(parseDateTime(existingAppointment?.endAt).let { if (it.isAfter(startDate)) it else startDate.plusHours(1) }) }
    var observations by remember { mutableStateOf(existingAppointment?.observations ?: "") }
    var serviceDescription by remember { mutableStateOf(existingAppointment?.serviceDescription ?: "") }
    val contactsList = remember { 
        mutableStateListOf<ContactDto>().apply {
            addAll(existingAppointment?.contacts?.map { ContactDto(it.name, it.phone, it.email) }.orEmpty())
        }
    }
    
    var isSubmitting by remember { mutableStateOf(false) }
    var errorMsg by remember { mutableStateOf<String?>(null) }

    val fmt = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm")

    // Debounced Nominatim search
    LaunchedEffect(locationText) {
        if (selectedLocation != null && locationText == selectedLocation?.displayName) {
            locationSuggestions = emptyList()
            showSuggestions = false
            return@LaunchedEffect
        }
        val parsedCoords = parseGoogleMaps(locationText)
        if (parsedCoords != null) {
            locationLat = parsedCoords.first
            locationLng = parsedCoords.second
            locationSuggestions = emptyList()
            showSuggestions = false
            coroutineScope.launch {
                val newAddr = reverseGeocode(parsedCoords.first, parsedCoords.second)
                if (newAddr != null) {
                    locationText = newAddr
                }
            }
            return@LaunchedEffect
        }

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
                Text(
                    text = if (existingAppointment != null) "Editar cita" else "Nueva cita",
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                    modifier = Modifier.weight(1f)
                )
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

                // Date Time Pickers
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        DateTimePickerField(
                            label = "Inicio",
                            value = startDate,
                            onValueChange = {
                                startDate = it
                                if (endDate.isBefore(it)) {
                                    endDate = it.plusHours(1)
                                }
                            },
                            modifier = Modifier.weight(1f)
                        )
                        DateTimePickerField(
                            label = "Fin",
                            value = endDate,
                            onValueChange = {
                                if (it.isAfter(startDate)) {
                                    endDate = it
                                }
                            },
                            modifier = Modifier.weight(1f)
                        )
                    }
                }

                // Location with autocomplete
                item {
                    Text("Ubicación", fontWeight = FontWeight.Medium, fontSize = 13.sp, modifier = Modifier.padding(bottom = 4.dp))
                    OutlinedTextField(
                        value = locationText,
                        onValueChange = { 
                            locationText = it
                            if (parseGoogleMaps(it) == null) {
                                locationLat = null
                                locationLng = null
                            }
                            selectedLocation = null 
                        },
                        placeholder = { Text("Escribe una dirección…", fontSize = 13.sp) },
                        leadingIcon = { Icon(Icons.Outlined.LocationOn, null, tint = Color(0xFFef4444)) },
                        trailingIcon = {
                            if (isSearching) CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp)
                            else if (locationText.length >= 3) IconButton(onClick = {
                                coroutineScope.launch {
                                    isSearching = true
                                    runCatching { searchNominatim(locationText).firstOrNull() }
                                        .getOrNull()
                                        ?.let { result ->
                                            locationText = result.displayName
                                            locationLat = result.lat
                                            locationLng = result.lon
                                            selectedLocation = result
                                            showSuggestions = false
                                        }
                                    isSearching = false
                                }
                            }) {
                                Icon(Icons.Outlined.Search, contentDescription = "Ubicar direccion")
                            }
                            else if (locationText.isNotEmpty()) IconButton(onClick = { 
                                locationText = ""
                                locationLat = null
                                locationLng = null
                                selectedLocation = null
                                locationSuggestions = emptyList()
                                showSuggestions = false 
                            }) {
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
                                                locationLat = result.lat
                                                locationLng = result.lon
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
                    if (locationLat != null && locationLng != null) {
                        Spacer(Modifier.height(10.dp))
                        Card(shape = RoundedCornerShape(10.dp), modifier = Modifier.fillMaxWidth()) {
                            Column {
                                // Leaflet WebView Map
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(200.dp)
                                        .background(MaterialTheme.colorScheme.surfaceVariant)
                                ) {
                                    AndroidView(
                                        factory = { ctx ->
                                            android.webkit.WebView(ctx).apply {
                                                settings.javaScriptEnabled = true
                                                settings.domStorageEnabled = true
                                                webViewClient = WebViewClient()
                                                addJavascriptInterface(object {
                                                    @android.webkit.JavascriptInterface
                                                    fun onMarkerMoved(lat: Double, lng: Double) {
                                                        post {
                                                            locationLat = lat
                                                            locationLng = lng
                                                            coroutineScope.launch {
                                                                val newAddr = reverseGeocode(lat, lng)
                                                                if (newAddr != null) {
                                                                    locationText = newAddr
                                                                }
                                                            }
                                                        }
                                                    }
                                                }, "AndroidBridge")

                                                val html = getLeafletMapHtml(locationLat!!, locationLng!!)
                                                loadDataWithBaseURL("https://openstreetmap.org", html, "text/html", "UTF-8", null)
                                            }
                                        },
                                        update = { webView ->
                                            webView.evaluateJavascript("updateMarker(${locationLat!!}, ${locationLng!!})", null)
                                        },
                                        modifier = Modifier.fillMaxSize()
                                    )
                                }
                                Row(Modifier.padding(10.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    val enc = URLEncoder.encode(locationText, "UTF-8")
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

                // Contacts list
                item {
                    Text("Contactos", fontWeight = FontWeight.Bold, fontSize = 14.sp, modifier = Modifier.padding(vertical = 8.dp))
                }
                itemsIndexed(contactsList) { index, contact ->
                    Card(
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.4f))
                    ) {
                        Column(Modifier.padding(12.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text("Contacto #${index + 1}", fontWeight = FontWeight.SemiBold, fontSize = 12.sp, modifier = Modifier.weight(1f))
                                IconButton(onClick = { contactsList.removeAt(index) }, modifier = Modifier.size(24.dp)) {
                                    Icon(Icons.Default.Close, "Eliminar contacto", modifier = Modifier.size(16.dp))
                                }
                            }
                            Spacer(Modifier.height(4.dp))
                            OutlinedTextField(
                                value = contact.name ?: "",
                                onValueChange = { contactsList[index] = contact.copy(name = it) },
                                label = { Text("Nombre") },
                                modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
                                singleLine = true
                            )
                            OutlinedTextField(
                                value = contact.phone ?: "",
                                onValueChange = { contactsList[index] = contact.copy(phone = it) },
                                label = { Text("Teléfono") },
                                modifier = Modifier.fillMaxWidth().padding(bottom = 4.dp),
                                singleLine = true
                            )
                            OutlinedTextField(
                                value = contact.email ?: "",
                                onValueChange = { contactsList[index] = contact.copy(email = it) },
                                label = { Text("Correo") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                        }
                    }
                }
                item {
                    OutlinedButton(
                        onClick = { contactsList.add(ContactDto("", "", "")) },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp)
                    ) {
                        Icon(Icons.Default.Add, null)
                        Spacer(Modifier.width(4.dp))
                        Text("Agregar contacto")
                    }
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

                    Row(
                        modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        if (existingAppointment != null) {
                            Button(
                                onClick = {
                                    isSubmitting = true
                                    errorMsg = null
                                    viewModel.deleteAppointment(existingAppointment.id)
                                    isSubmitting = false
                                    onDismiss()
                                },
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = MaterialTheme.colorScheme.error,
                                    contentColor = MaterialTheme.colorScheme.onError
                                ),
                                modifier = Modifier.weight(1f),
                                enabled = !isSubmitting,
                            ) {
                                Icon(Icons.Default.Delete, null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(4.dp))
                                Text("Eliminar")
                            }
                        }
                        
                        Button(
                            onClick = {
                                if (title.isBlank()) { errorMsg = "El título es obligatorio"; return@Button }
                                if (!endDate.isAfter(startDate)) {
                                    errorMsg = "La fecha de fin debe ser posterior al inicio"
                                    return@Button
                                }
                                isSubmitting = true
                                errorMsg = null
                                
                                val req = CreateAppointmentRequest(
                                    title = title,
                                    clientId = existingAppointment?.clientId,
                                    startAt = startDate.format(fmt),
                                    endAt = endDate.format(fmt),
                                    location = locationText.ifBlank { null },
                                    locationLat = locationLat,
                                    locationLng = locationLng,
                                    serviceDescription = serviceDescription.ifBlank { null },
                                    observations = observations.ifBlank { null },
                                    contacts = contactsList.filter { !it.name.isNullOrBlank() || !it.phone.isNullOrBlank() || !it.email.isNullOrBlank() },
                                    status = status.key,
                                )
                                
                                if (existingAppointment != null) {
                                    viewModel.updateAppointment(
                                        existingAppointment.id,
                                        req,
                                        onSuccess = { isSubmitting = false; onDismiss() },
                                        onError = { errorMsg = it; isSubmitting = false }
                                    )
                                } else {
                                    viewModel.createAppointment(
                                        req,
                                        onSuccess = { isSubmitting = false; onDismiss() },
                                        onError = { errorMsg = it; isSubmitting = false },
                                    )
                                }
                            },
                            modifier = Modifier.weight(2f),
                            enabled = !isSubmitting,
                        ) {
                            if (isSubmitting) CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = Color.White)
                            else Text(if (existingAppointment != null) "Guardar cambios" else "Agendar cita")
                        }
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

@Composable
fun DateTimePickerField(
    label: String,
    value: LocalDateTime,
    onValueChange: (LocalDateTime) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val formatted = remember(value) {
        val dateFmt = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm")
        value.format(dateFmt)
    }

    Box(modifier = modifier.clickable {
        val datePickerDialog = android.app.DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                val timePickerDialog = android.app.TimePickerDialog(
                    context,
                    { _, hourOfDay, minute ->
                        val selected = LocalDateTime.of(year, month + 1, dayOfMonth, hourOfDay, minute)
                        onValueChange(selected)
                    },
                    value.hour,
                    value.minute,
                    true
                )
                timePickerDialog.show()
            },
            value.year,
            value.monthValue - 1,
            value.dayOfMonth
        )
        datePickerDialog.show()
    }) {
        OutlinedTextField(
            value = formatted,
            onValueChange = {},
            label = { Text(label) },
            readOnly = true,
            enabled = false,
            modifier = Modifier.fillMaxWidth(),
            colors = OutlinedTextFieldDefaults.colors(
                disabledTextColor = MaterialTheme.colorScheme.onSurface,
                disabledBorderColor = MaterialTheme.colorScheme.outline,
                disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant
            )
        )
    }
}

private fun parseDateTime(dateStr: String?): LocalDateTime {
    if (dateStr.isNullOrBlank()) return LocalDateTime.now().withMinute(0).plusHours(1)
    return runCatching {
        val clean = dateStr.replace(" ", "T")
        LocalDateTime.parse(clean.substring(0, 16))
    }.getOrElse {
        LocalDateTime.now().withMinute(0).plusHours(1)
    }
}

private fun parseGoogleMaps(text: String): Pair<Double, Double>? {
    val patterns = listOf(
        Regex("""@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)"""),            // .../@41.38,2.17
        Regex("""[?&]q=(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)"""),        // ...?q=41.38,2.17
        Regex("""!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)"""),          // ...!3d41.38!4d2.17
        Regex("""^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$""")  // "41.38, 2.17"
    )
    for (re in patterns) {
        val m = re.find(text)
        if (m != null && m.groupValues.size >= 3) {
            val lat = m.groupValues[1].toDoubleOrNull()
            val lng = m.groupValues[2].toDoubleOrNull()
            if (lat != null && lng != null) {
                return Pair(lat, lng)
            }
        }
    }
    return null
}

private suspend fun reverseGeocode(lat: Double, lng: Double): String? = withContext(Dispatchers.IO) {
    try {
        val url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=18&addressdetails=1"
        val conn = URL(url).openConnection()
        conn.setRequestProperty("User-Agent", "FacturaPro/1.0")
        conn.setRequestProperty("Accept-Language", "es")
        val json = conn.getInputStream().bufferedReader().readText()
        val obj = org.json.JSONObject(json)
        obj.optString("display_name").ifBlank { null }
    } catch (e: Exception) {
        null
    }
}

private fun getLeafletMapHtml(lat: Double, lng: Double): String {
    return """
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <style>
                html, body { margin: 0; padding: 0; width: 100%; height: 100%; }
                #map { height: 100%; width: 100%; }
            </style>
        </head>
        <body>
            <div id="map"></div>
            <script>
                var map = L.map('map', { zoomControl: false }).setView([$lat, $lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);
                var marker = L.marker([$lat, $lng], { draggable: true }).addTo(map);

                marker.on('dragend', function() {
                    var pos = marker.getLatLng();
                    AndroidBridge.onMarkerMoved(pos.lat, pos.lng);
                });

                map.on('click', function(e) {
                    marker.setLatLng(e.latlng);
                    AndroidBridge.onMarkerMoved(e.latlng.lat, e.latlng.lng);
                });

                function updateMarker(lat, lng) {
                    if (typeof marker !== 'undefined') {
                        var latlng = L.latLng(lat, lng);
                        marker.setLatLng(latlng);
                        map.setView(latlng, map.getZoom());
                    }
                }
            </script>
        </body>
        </html>
    """.trimIndent()
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
            lat = obj.optString("lat").toDoubleOrNull() ?: obj.optDouble("lat", 0.0),
            lon = obj.optString("lon").toDoubleOrNull() ?: obj.optDouble("lon", 0.0),
        )
    }
}
