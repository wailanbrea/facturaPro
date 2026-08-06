package com.facturador.facturapro.ui.invoices

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.Modifier
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
    technicianName: String,
    onTechnicianName: (String) -> Unit,
    workReference: String,
    onWorkReference: (String) -> Unit,
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
    item {
        OutlinedTextField(
            value = technicianName,
            onValueChange = onTechnicianName,
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Tecnico asignado") },
            singleLine = true,
        )
    }
    item {
        OutlinedTextField(
            value = workReference,
            onValueChange = onWorkReference,
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Referencia / obra") },
            singleLine = true,
        )
    }
    item {
        OutlinedTextField(
            value = serviceLocation,
            onValueChange = onServiceLocation,
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Lugar de intervencion") },
            singleLine = true,
        )
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
) {
    if (isQuotation) {
        item { SectionHeading("Alcance del presupuesto") }
        item {
            OutlinedTextField(
                value = intervention.serviceScope.orEmpty(),
                onValueChange = { onChange(intervention.copy(serviceScope = it)) },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Alcance del servicio") },
                minLines = 2,
            )
        }
        item {
            OutlinedTextField(
                value = intervention.includedItems.orEmpty(),
                onValueChange = { onChange(intervention.copy(includedItems = it)) },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Incluye (un concepto por linea)") },
                minLines = 3,
            )
        }
        return
    }

    item { SectionHeading("Equipo e intervencion tecnica") }
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
            label = { Text("Numero de serie") },
            singleLine = true,
        )
    }
    item {
        OutlinedTextField(
            value = intervention.equipmentLocation.orEmpty(),
            onValueChange = { onChange(intervention.copy(equipmentLocation = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Ubicacion del equipo") },
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
        OutlinedTextField(
            value = intervention.diagnosticSummary.orEmpty(),
            onValueChange = { onChange(intervention.copy(diagnosticSummary = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Diagnostico tecnico") },
            minLines = 2,
        )
    }
    item {
        OutlinedTextField(
            value = intervention.technicalConclusions.orEmpty(),
            onValueChange = { onChange(intervention.copy(technicalConclusions = it)) },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Conclusiones tecnicas") },
            minLines = 2,
        )
    }
}
