package com.facturador.facturapro.ui.invoices

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.facturador.facturapro.domain.model.Intervention

/**
 * Secciones del formulario de factura y presupuesto.
 *
 * Viven en su propio fichero a proposito: el composable del formulario ya era
 * enorme y, al crecer, el compilador de Compose se quedaba sin memoria
 * ("GC overhead limit exceeded"). Partirlo tambien lo hace legible.
 */

/** Titulo de bloque dentro del formulario. */
@Composable
internal fun SectionHeading(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.titleSmall,
        fontWeight = FontWeight.SemiBold,
        color = MaterialTheme.colorScheme.primary,
    )
}

/** Descuento, desplazamiento y datos de cabecera comunes a ambos documentos. */
internal fun LazyListScope.commercialFields(
    discountPercent: String,
    onDiscountPercent: (String) -> Unit,
    travelAmount: String,
    onTravelAmount: (String) -> Unit,
    isQuotation: Boolean,
    serviceLocation: String,
    onServiceLocation: (String) -> Unit,
) {
    item { SectionHeading("Datos comerciales") }
    item {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            OutlinedTextField(
                value = discountPercent,
                onValueChange = onDiscountPercent,
                modifier = Modifier.weight(1f),
                label = { Text("Descuento (%)") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            )
            OutlinedTextField(
                value = travelAmount,
                onValueChange = onTravelAmount,
                modifier = Modifier.weight(1f),
                label = { Text("Desplazamiento") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            )
        }
    }
    if (isQuotation) {
        item {
            OutlinedTextField(
                value = serviceLocation,
                onValueChange = onServiceLocation,
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Lugar de intervención") },
                singleLine = true,
            )
        }
    }
}

/**
 * Bloque tecnico. Cada tipo de documento muestra solo el suyo, igual que la web:
 * alcance e incluye para el presupuesto, equipo y diagnostico para la factura.
 */
internal fun LazyListScope.interventionFields(
    isQuotation: Boolean,
    intervention: Intervention,
    onChange: (Intervention) -> Unit,
    textFieldsEditable: Boolean = false,
    onRequestUnlock: (() -> Unit)? = null,
) {
    if (isQuotation) {
        item { SectionHeading("Contenido del presupuesto") }
        item {
            Box(modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = intervention.serviceScope.orEmpty(),
                    onValueChange = { onChange(intervention.copy(serviceScope = it)) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Alcance del servicio") },
                    minLines = 2,
                    readOnly = !textFieldsEditable,
                    enabled = textFieldsEditable,
                    trailingIcon = if (!textFieldsEditable && onRequestUnlock != null) {
                        {
                            IconButton(onClick = onRequestUnlock) {
                                Icon(
                                    imageVector = Icons.Outlined.Lock,
                                    contentDescription = "Bloqueado. Toca para habilitar edición",
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    } else null,
                    colors = OutlinedTextFieldDefaults.colors(
                        disabledTextColor = MaterialTheme.colorScheme.onSurface,
                        disabledBorderColor = MaterialTheme.colorScheme.outline,
                        disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    ),
                )
                if (!textFieldsEditable && onRequestUnlock != null) {
                    Box(
                        modifier = Modifier
                            .matchParentSize()
                            .clickable(onClick = onRequestUnlock),
                    )
                }
            }
        }
        item { Spacer(Modifier.height(8.dp)) }
        item {
            Box(modifier = Modifier.fillMaxWidth()) {
                OutlinedTextField(
                    value = intervention.includedItems.orEmpty(),
                    onValueChange = { onChange(intervention.copy(includedItems = it)) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Incluye (un concepto por línea)") },
                    minLines = 3,
                    readOnly = !textFieldsEditable,
                    enabled = textFieldsEditable,
                    trailingIcon = if (!textFieldsEditable && onRequestUnlock != null) {
                        {
                            IconButton(onClick = onRequestUnlock) {
                                Icon(
                                    imageVector = Icons.Outlined.Lock,
                                    contentDescription = "Bloqueado. Toca para habilitar edición",
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    } else null,
                    colors = OutlinedTextFieldDefaults.colors(
                        disabledTextColor = MaterialTheme.colorScheme.onSurface,
                        disabledBorderColor = MaterialTheme.colorScheme.outline,
                        disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    ),
                )
                if (!textFieldsEditable && onRequestUnlock != null) {
                    Box(
                        modifier = Modifier
                            .matchParentSize()
                            .clickable(onClick = onRequestUnlock),
                    )
                }
            }
        }
        return
    }

    item { SectionHeading("Equipo e intervención técnica") }
    item {
        OutlinedTextField(
            value = intervention.equipmentType.orEmpty(),
            onValueChange = { onChange(intervention.copy(equipmentType = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Equipo") },
            singleLine = true,
        )
    }
    item {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            OutlinedTextField(
                value = intervention.equipmentBrand.orEmpty(),
                onValueChange = { onChange(intervention.copy(equipmentBrand = it)) },
                modifier = Modifier.weight(1f),
                label = { Text("Fabricante") },
                singleLine = true,
            )
            OutlinedTextField(
                value = intervention.equipmentModel.orEmpty(),
                onValueChange = { onChange(intervention.copy(equipmentModel = it)) },
                modifier = Modifier.weight(1f),
                label = { Text("Modelo") },
                singleLine = true,
            )
        }
    }
    item {
        OutlinedTextField(
            value = intervention.equipmentSerial.orEmpty(),
            onValueChange = { onChange(intervention.copy(equipmentSerial = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Número de serie") },
            singleLine = true,
        )
    }
    item {
        OutlinedTextField(
            value = intervention.equipmentLocation.orEmpty(),
            onValueChange = { onChange(intervention.copy(equipmentLocation = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Ubicación del equipo") },
            singleLine = true,
        )
    }
    item {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            OutlinedTextField(
                value = intervention.unitsIndoor?.toString().orEmpty(),
                onValueChange = { onChange(intervention.copy(unitsIndoor = it.filter(Char::isDigit).toIntOrNull())) },
                modifier = Modifier.weight(1f),
                label = { Text("Uds. interior") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            )
            OutlinedTextField(
                value = intervention.unitsOutdoor?.toString().orEmpty(),
                onValueChange = { onChange(intervention.copy(unitsOutdoor = it.filter(Char::isDigit).toIntOrNull())) },
                modifier = Modifier.weight(1f),
                label = { Text("Uds. exterior") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            )
        }
    }
    item {
        Box(modifier = Modifier.fillMaxWidth()) {
            OutlinedTextField(
                value = intervention.diagnosticSummary.orEmpty(),
                onValueChange = {
                    if (it.length <= 300) {
                        onChange(intervention.copy(diagnosticSummary = it))
                    }
                },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Diagnóstico técnico") },
                placeholder = { Text("Resumen del diagnóstico técnico realizado...") },
                supportingText = { Text("${intervention.diagnosticSummary?.length ?: 0}/300") },
                minLines = 3,
                readOnly = !textFieldsEditable,
                enabled = textFieldsEditable,
                trailingIcon = if (!textFieldsEditable && onRequestUnlock != null) {
                    {
                        IconButton(onClick = onRequestUnlock) {
                            Icon(
                                imageVector = Icons.Outlined.Lock,
                                contentDescription = "Bloqueado. Toca para habilitar edición",
                                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                } else null,
                colors = OutlinedTextFieldDefaults.colors(
                    disabledTextColor = MaterialTheme.colorScheme.onSurface,
                    disabledBorderColor = MaterialTheme.colorScheme.outline,
                    disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant,
                ),
            )
            if (!textFieldsEditable && onRequestUnlock != null) {
                Box(
                    modifier = Modifier
                        .matchParentSize()
                        .clickable(onClick = onRequestUnlock),
                )
            }
        }
    }
    item {
        Box(modifier = Modifier.fillMaxWidth()) {
            OutlinedTextField(
                value = intervention.technicalConclusions.orEmpty(),
                onValueChange = {
                    if (it.length <= 280) {
                        onChange(intervention.copy(technicalConclusions = it))
                    }
                },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Conclusiones técnicas") },
                placeholder = { Text("Conclusiones y recomendaciones de la intervención...") },
                supportingText = { Text("${intervention.technicalConclusions?.length ?: 0}/280") },
                minLines = 3,
                readOnly = !textFieldsEditable,
                enabled = textFieldsEditable,
                trailingIcon = if (!textFieldsEditable && onRequestUnlock != null) {
                    {
                        IconButton(onClick = onRequestUnlock) {
                            Icon(
                                imageVector = Icons.Outlined.Lock,
                                contentDescription = "Bloqueado. Toca para habilitar edición",
                                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                } else null,
                colors = OutlinedTextFieldDefaults.colors(
                    disabledTextColor = MaterialTheme.colorScheme.onSurface,
                    disabledBorderColor = MaterialTheme.colorScheme.outline,
                    disabledLabelColor = MaterialTheme.colorScheme.onSurfaceVariant,
                ),
            )
            if (!textFieldsEditable && onRequestUnlock != null) {
                Box(
                    modifier = Modifier
                        .matchParentSize()
                        .clickable(onClick = onRequestUnlock),
                )
            }
        }
    }
}
