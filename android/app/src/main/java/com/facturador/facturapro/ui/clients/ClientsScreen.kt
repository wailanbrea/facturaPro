package com.facturador.facturapro.ui.clients

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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.automirrored.outlined.Note
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Mail
import androidx.compose.material.icons.outlined.Phone
import androidx.compose.material.icons.outlined.PersonOutline
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.facturador.facturapro.domain.model.ClientDraft
import com.facturador.facturapro.domain.model.ClientRecord
import com.facturador.facturapro.ui.common.EmptyState
import com.facturador.facturapro.ui.common.ErrorBanner
import com.facturador.facturapro.ui.common.InitialAvatar
import com.facturador.facturapro.ui.common.InlineLoader
import com.facturador.facturapro.ui.common.SectionCard
import com.facturador.facturapro.ui.common.SectionTitle
import com.facturador.facturapro.ui.theme.OutlineVariant

@Composable
fun ClientsScreen(
    state: ClientsUiState,
    onSearchChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onCreateClient: (ClientDraft) -> Unit,
    onUpdateClient: (Long, ClientDraft) -> Unit,
    onStartEdit: (ClientRecord) -> Unit,
    onEndEdit: () -> Unit,
    onConsumeSaveEvent: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var isCreating by rememberSaveable { mutableStateOf(false) }
    val editing = state.editingClient

    LaunchedEffect(state.savedClientId) {
        if (state.savedClientId != null) {
            isCreating = false
            onConsumeSaveEvent()
        }
    }

    if (isCreating || editing != null) {
        ClientFormScreen(
            existing = editing,
            isSaving = state.isSaving,
            errorMessage = state.errorMessage,
            onCancel = {
                isCreating = false
                onEndEdit()
            },
            onSave = { draft ->
                if (editing != null) {
                    onUpdateClient(editing.id, draft)
                } else {
                    onCreateClient(draft)
                }
            },
            modifier = modifier,
        )
        return
    }

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 16.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            SectionTitle(
                title = "Clientes",
                subtitle = "${state.clients.size} contactos · directorio fiscal",
                trailing = {
                    Surface(
                        modifier = Modifier.clickable { isCreating = true },
                        color = MaterialTheme.colorScheme.primary,
                        shape = RoundedCornerShape(50),
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(
                                imageVector = Icons.Outlined.PersonOutline,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.onPrimary,
                                modifier = Modifier.size(16.dp),
                            )
                            Spacer(Modifier.size(6.dp))
                            Text(
                                text = "Nuevo",
                                style = MaterialTheme.typography.labelMedium,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onPrimary,
                            )
                        }
                    }
                },
            )
        }

        item {
            ClientSearchField(
                value = state.searchQuery,
                onChange = onSearchChanged,
                onSubmit = onRefresh,
            )
        }

        if (state.isLoading) {
            item { InlineLoader() }
        }

        state.errorMessage?.let { message ->
            item { ErrorBanner(message = message) }
        }

        if (!state.isLoading && state.clients.isEmpty()) {
            item {
                EmptyState(
                    icon = Icons.Outlined.PersonOutline,
                    title = "No hay clientes",
                    body = "Crea el primer cliente con el botón Nuevo o revisa los permisos del usuario.",
                )
            }
        }

        items(state.clients, key = { it.id }) { client ->
            ClientCard(client = client, onClick = { onStartEdit(client) })
        }
    }
}

@Composable
private fun ClientCard(client: ClientRecord, onClick: () -> Unit) {
    SectionCard(
        modifier = Modifier.clickable(onClick = onClick),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                InitialAvatar(text = client.name, size = 46.dp)
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = client.name,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    val sub = client.taxId?.takeIf { it.isNotBlank() }?.let { "RNC · $it" }
                        ?: client.email?.takeIf { it.isNotBlank() }
                        ?: client.phone?.takeIf { it.isNotBlank() }
                    if (sub != null) {
                        Text(
                            text = sub,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                Icon(
                    imageVector = Icons.Outlined.ChevronRight,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            val infoRows = buildList {
                client.email?.takeIf { it.isNotBlank() }?.let { add(Icons.Outlined.Mail to it) }
                client.phone?.takeIf { it.isNotBlank() }?.let { add(Icons.Outlined.Phone to it) }
            }
            if (infoRows.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    infoRows.forEach { (icon, value) ->
                        ContactPill(icon = icon, label = value)
                    }
                }
            }
        }
    }
}

@Composable
private fun ContactPill(icon: ImageVector, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            modifier = Modifier
                .size(28.dp)
                .background(
                    MaterialTheme.colorScheme.surfaceContainerLow,
                    RoundedCornerShape(8.dp),
                ),
            contentAlignment = Alignment.Center,
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(15.dp),
            )
        }
        Spacer(Modifier.size(10.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ClientSearchField(
    value: String,
    onChange: (String) -> Unit,
    onSubmit: () -> Unit,
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .height(52.dp),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(14.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
    ) {
        TextField(
            value = value,
            onValueChange = onChange,
            placeholder = {
                Text(
                    text = "Buscar por nombre, email o RNC",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.8f),
                )
            },
            leadingIcon = {
                Icon(
                    imageVector = Icons.Outlined.Search,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            },
            singleLine = true,
            textStyle = MaterialTheme.typography.bodyMedium,
            colors = TextFieldDefaults.colors(
                focusedContainerColor = Color.Transparent,
                unfocusedContainerColor = Color.Transparent,
                focusedIndicatorColor = Color.Transparent,
                unfocusedIndicatorColor = Color.Transparent,
                cursorColor = MaterialTheme.colorScheme.primary,
            ),
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun ClientFormScreen(
    existing: ClientRecord?,
    isSaving: Boolean,
    errorMessage: String?,
    onCancel: () -> Unit,
    onSave: (ClientDraft) -> Unit,
    modifier: Modifier = Modifier,
) {
    var name by rememberSaveable(existing?.id) { mutableStateOf(existing?.name.orEmpty()) }
    var taxId by rememberSaveable(existing?.id) { mutableStateOf(existing?.taxId.orEmpty()) }
    var address by rememberSaveable(existing?.id) { mutableStateOf(existing?.address.orEmpty()) }
    var city by rememberSaveable(existing?.id) { mutableStateOf(existing?.city.orEmpty()) }
    var phone by rememberSaveable(existing?.id) { mutableStateOf(existing?.phone.orEmpty()) }
    var email by rememberSaveable(existing?.id) { mutableStateOf(existing?.email.orEmpty()) }
    var notes by rememberSaveable(existing?.id) { mutableStateOf(existing?.notes.orEmpty()) }

    val isEditing = existing != null
    val title = if (isEditing) "Editar cliente" else "Nuevo cliente"
    val ctaLabel = if (isEditing) "Guardar cambios" else "Guardar cliente"

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                IconButton(onClick = onCancel) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                        contentDescription = "Volver",
                        tint = MaterialTheme.colorScheme.onSurface,
                    )
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                    if (isEditing) {
                        Text(
                            text = "ID #${existing!!.id}",
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }

        item {
            FormSection(title = "Identidad", icon = Icons.Outlined.Business) {
                FormTextField(
                    value = name,
                    onValueChange = { name = it },
                    label = "Nombre / Razón social",
                )
                FormTextField(
                    value = taxId,
                    onValueChange = { taxId = it },
                    label = "RNC / Cédula",
                    icon = Icons.Outlined.Badge,
                )
            }
        }

        item {
            FormSection(title = "Contacto", icon = Icons.Outlined.Phone) {
                FormTextField(
                    value = email,
                    onValueChange = { email = it },
                    label = "Correo electrónico",
                    keyboardType = KeyboardType.Email,
                    icon = Icons.Outlined.Mail,
                )
                FormTextField(
                    value = phone,
                    onValueChange = { phone = it },
                    label = "Teléfono",
                    keyboardType = KeyboardType.Phone,
                    icon = Icons.Outlined.Phone,
                )
                FormTextField(
                    value = address,
                    onValueChange = { address = it },
                    label = "Dirección",
                    icon = Icons.Outlined.LocationOn,
                )
                FormTextField(
                    value = city,
                    onValueChange = { city = it },
                    label = "Ciudad",
                )
            }
        }

        item {
            FormSection(title = "Notas internas", icon = Icons.AutoMirrored.Outlined.Note) {
                FormTextField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = "Observaciones, condiciones, etc.",
                    minLines = 3,
                )
            }
        }

        errorMessage?.let { message ->
            item { ErrorBanner(message = message) }
        }

        item {
            Spacer(Modifier.height(4.dp))
            Button(
                onClick = {
                    onSave(
                        ClientDraft(
                            name = name,
                            taxId = taxId,
                            address = address,
                            city = city,
                            phone = phone,
                            email = email,
                            notes = notes,
                        ),
                    )
                },
                enabled = !isSaving && name.isNotBlank(),
                modifier = Modifier
                    .fillMaxWidth()
                    .height(54.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary,
                ),
            ) {
                if (isSaving) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        strokeWidth = 2.dp,
                        color = MaterialTheme.colorScheme.onPrimary,
                    )
                } else {
                    Text(
                        text = ctaLabel,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
        }
    }
}

@Composable
private fun FormSection(
    title: String,
    icon: ImageVector,
    content: @Composable () -> Unit,
) {
    SectionCard {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .background(
                            MaterialTheme.colorScheme.primary.copy(alpha = 0.1f),
                            RoundedCornerShape(10.dp),
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(18.dp),
                    )
                }
                Spacer(Modifier.size(10.dp))
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
            }
            content()
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FormTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    icon: ImageVector? = null,
    keyboardType: KeyboardType = KeyboardType.Text,
    minLines: Int = 1,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = Modifier.fillMaxWidth(),
        label = { Text(label) },
        leadingIcon = icon?.let {
            {
                Icon(
                    imageVector = it,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        },
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        singleLine = minLines == 1,
        minLines = minLines,
        shape = RoundedCornerShape(12.dp),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = MaterialTheme.colorScheme.primary,
            unfocusedBorderColor = OutlineVariant,
        ),
    )
}
