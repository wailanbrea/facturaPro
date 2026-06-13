package com.facturador.facturapro.ui.invoices

import android.content.Intent
import android.content.ActivityNotFoundException
import android.content.Context
import android.print.PrintAttributes
import android.print.PrintManager
import android.widget.Toast
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
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.automirrored.outlined.Send
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.OpenInBrowser
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Print
import androidx.compose.material.icons.outlined.RemoveRedEye
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuAnchorType
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.FileProvider
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.ClientRecord
import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceDraftItem
import com.facturador.facturapro.domain.model.InvoiceSummary
import com.facturador.facturapro.ui.common.EmptyState
import com.facturador.facturapro.ui.common.ErrorBanner
import com.facturador.facturapro.ui.common.InitialAvatar
import com.facturador.facturapro.ui.common.InlineLoader
import com.facturador.facturapro.ui.common.IsoDatePickerField
import com.facturador.facturapro.ui.common.SectionCard
import com.facturador.facturapro.ui.common.SectionTitle
import com.facturador.facturapro.ui.common.StatusBadge
import com.facturador.facturapro.ui.common.formatMoney
import com.facturador.facturapro.ui.common.invoiceStatusLabel
import com.facturador.facturapro.ui.common.statusColors
import com.facturador.facturapro.ui.theme.OutlineVariant
import java.io.File
import java.math.BigDecimal
import java.math.RoundingMode
import java.time.LocalDate
import android.webkit.WebView
import android.webkit.WebViewClient

@Composable
fun InvoicesScreen(
    state: InvoicesUiState,
    clients: List<ClientRecord>,
    bootstrap: BootstrapCatalogs?,
    openCreateRequest: Int,
    openInvoiceRequest: Int,
    requestedInvoiceId: Long?,
    openRequestedInvoiceForEdit: Boolean,
    onSearchChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onSelectInvoice: (Long) -> Unit,
    onClearSelection: () -> Unit,
    onCreateInvoice: (InvoiceDraft) -> Unit,
    onUpdateInvoice: (Long, InvoiceDraft) -> Unit,
    onIssueInvoice: () -> Unit,
    onLoadPreview: () -> Unit,
    onLoadIssuePreview: () -> Unit,
    onLoadDraftPreview: (InvoiceDraft) -> Unit,
    onClearPreview: () -> Unit,
    onGeneratePdf: () -> Unit,
    onDownloadPdf: () -> Unit,
    onPrintPdf: () -> Unit,
    onSharePdfToWhatsApp: () -> Unit,
    onViewPdf: () -> Unit,
    onIssueAndPreparePdf: (InvoicePdfAction) -> Unit,
    onConsumeSavedEvent: () -> Unit,
    onConsumeSharedPdfEvent: () -> Unit,
    onClearInternalPdf: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    var pane by rememberSaveable { mutableStateOf(InvoicePane.List) }
    var editingInvoiceId by rememberSaveable { mutableStateOf<Long?>(null) }

    LaunchedEffect(openCreateRequest) {
        if (openCreateRequest > 0) {
            pane = InvoicePane.Create
            editingInvoiceId = null
            onClearSelection()
            onClearPreview()
        }
    }

    LaunchedEffect(openInvoiceRequest, requestedInvoiceId, openRequestedInvoiceForEdit) {
        val invoiceId = requestedInvoiceId ?: return@LaunchedEffect
        if (openInvoiceRequest > 0) {
            editingInvoiceId = invoiceId
            pane = if (openRequestedInvoiceForEdit) InvoicePane.Edit else InvoicePane.Detail
            onClearPreview()
            onSelectInvoice(invoiceId)
        }
    }

    LaunchedEffect(state.savedInvoiceId) {
        if (state.savedInvoiceId != null) {
            pane = InvoicePane.Detail
            editingInvoiceId = state.savedInvoiceId
            onSelectInvoice(state.savedInvoiceId)
            onConsumeSavedEvent()
        }
    }

    LaunchedEffect(state.internalPdfPath) {
        if (state.internalPdfPath != null) {
            pane = InvoicePane.PdfViewer
        }
    }

    LaunchedEffect(state.pendingPdfAction) {
        val pendingAction = state.pendingPdfAction ?: return@LaunchedEffect
        when (pendingAction.action) {
            InvoicePdfAction.Share -> sharePdf(context = context, absolutePath = pendingAction.absolutePath)
            InvoicePdfAction.WhatsApp -> sharePdfToWhatsApp(context = context, absolutePath = pendingAction.absolutePath)
            InvoicePdfAction.Print -> printPdf(context = context, absolutePath = pendingAction.absolutePath)
            InvoicePdfAction.View -> {
                // Legacy fallback. The normal View flow now uses PdfViewerScreen.
                viewPdf(context = context, absolutePath = pendingAction.absolutePath)
            }
        }
        onConsumeSharedPdfEvent()
    }

    when (pane) {
        InvoicePane.List -> InvoiceListPane(
            state = state,
            onSearchChanged = onSearchChanged,
            onRefresh = onRefresh,
            onNewInvoice = {
                pane = InvoicePane.Create
                editingInvoiceId = null
            },
            onSelectInvoice = {
                editingInvoiceId = it
                pane = InvoicePane.Detail
                onSelectInvoice(it)
            },
            modifier = modifier,
        )

        InvoicePane.Detail -> InvoiceDetailPane(
            state = state,
            onBack = {
                pane = InvoicePane.List
                editingInvoiceId = null
                onClearSelection()
                onClearPreview()
            },
            onEdit = {
                pane = InvoicePane.Edit
            },
            onIssueInvoice = onIssueInvoice,
            onViewPdf = onViewPdf,
            onGeneratePdf = onGeneratePdf,
            onDownloadPdf = onDownloadPdf,
            onPrintPdf = onPrintPdf,
            onSharePdfToWhatsApp = onSharePdfToWhatsApp,
            onIssueAndPreparePdf = onIssueAndPreparePdf,
            modifier = modifier,
        )

        InvoicePane.Preview -> InvoicePreviewPane(
            state = state,
            onBack = {
                pane = InvoicePane.Detail
                onClearPreview()
            },
            modifier = modifier,
        )

        InvoicePane.PdfViewer -> {
            val pdfPath = state.internalPdfPath

            if (pdfPath == null) {
                pane = InvoicePane.Detail
            } else {
                PdfViewerScreen(
                    filePath = pdfPath,
                    onBack = {
                        pane = InvoicePane.Detail
                        onClearInternalPdf()
                    },
                    modifier = modifier,
                )
            }
        }

        InvoicePane.Create -> InvoiceFormPane(
            title = "Nueva factura",
            clients = clients,
            bootstrap = bootstrap,
            existingInvoice = null,
            isSaving = state.isSaving,
            isPreviewLoading = state.isPreviewLoading,
            previewHtml = state.previewHtml,
            errorMessage = state.errorMessage,
            onBack = { pane = InvoicePane.List },
            onSave = onCreateInvoice,
            onPreview = onLoadDraftPreview,
            onClearPreview = onClearPreview,
            modifier = modifier,
        )

        InvoicePane.Edit -> {
            if (state.selectedInvoice?.id != editingInvoiceId) {
                InvoiceLoadingPane(
                    onBack = {
                        pane = InvoicePane.List
                        editingInvoiceId = null
                        onClearSelection()
                    },
                    modifier = modifier,
                )
            } else {
                InvoiceFormPane(
                    title = "Editar factura",
                    clients = clients,
                    bootstrap = bootstrap,
                    existingInvoice = state.selectedInvoice,
                    isSaving = state.isSaving,
                    isPreviewLoading = state.isPreviewLoading,
                    previewHtml = state.previewHtml,
                    errorMessage = state.errorMessage,
                    onBack = { pane = InvoicePane.Detail },
                    onSave = { draft ->
                        val invoiceId = state.selectedInvoice?.id ?: return@InvoiceFormPane
                        onUpdateInvoice(invoiceId, draft)
                    },
                    onPreview = onLoadDraftPreview,
                    onClearPreview = onClearPreview,
                    modifier = modifier,
                )
            }
        }
    }
}

@Composable
private fun InvoiceLoadingPane(
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item { ToolbarRow(title = "Cargando factura", onBack = onBack) }
        item { InlineLoader() }
    }
}

@Composable
private fun InvoiceListPane(
    state: InvoicesUiState,
    onSearchChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onNewInvoice: () -> Unit,
    onSelectInvoice: (Long) -> Unit,
    modifier: Modifier = Modifier,
) {
    var statusFilter by rememberSaveable { mutableStateOf<String?>(null) }
    val filteredInvoices = remember(state.invoices, statusFilter) {
        if (statusFilter == null) state.invoices
        else state.invoices.filter { matchesFilter(it.status, statusFilter!!) }
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
                title = "Facturas y presupuestos",
                subtitle = "${state.invoices.size} documentos · seguimiento ágil",
            )
        }

        item {
            SearchField(
                value = state.searchQuery,
                onValueChange = onSearchChanged,
                placeholder = "Buscar por cliente o número",
                onSubmit = onRefresh,
            )
        }

        item {
            FilterChips(
                selected = statusFilter,
                onSelect = { statusFilter = it },
            )
        }

        if (state.isLoading) {
            item { InlineLoader() }
        }

        state.errorMessage?.let { message ->
            item { ErrorBanner(message = message) }
        }

        if (!state.isLoading && filteredInvoices.isEmpty()) {
            item {
                EmptyState(
                    title = if (state.invoices.isEmpty()) "Aún no hay documentos"
                    else "Sin resultados con este filtro",
                    body = if (state.invoices.isEmpty())
                        "Crea el primer borrador con el botón + o ajusta los permisos del usuario."
                    else "Prueba con otro estado o limpia el filtro para ver todo.",
                )
            }
        }

        items(filteredInvoices, key = { it.id }) { invoice ->
            InvoiceSummaryCard(
                invoice = invoice,
                onClick = { onSelectInvoice(invoice.id) },
            )
        }
    }
}

private fun matchesFilter(status: String, filter: String): Boolean = when (filter) {
    "pending" -> status == "issued" || status == "partially_paid"
    else -> status == filter
}

private fun invoiceDocumentStatusLabel(status: String, documentType: String): String = when {
    documentType == "quotation" && status == "issued" -> "Emitido"
    documentType == "quotation" && status == "accepted" -> "Aceptado"
    documentType == "quotation" && status == "converted" -> "Convertido"
    documentType == "quotation" && status == "cancelled" -> "Anulado"
    else -> invoiceStatusLabel(status)
}

@Composable
private fun FilterChips(
    selected: String?,
    onSelect: (String?) -> Unit,
) {
    val chips = listOf(
        FilterChipDef(null, "Todas"),
        FilterChipDef("paid", "Pagadas"),
        FilterChipDef("pending", "Pendientes"),
        FilterChipDef("converted", "Convertidos"),
        FilterChipDef("overdue", "Vencidas"),
        FilterChipDef("draft", "Borradores"),
        FilterChipDef("cancelled", "Anuladas"),
    )
    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        items(chips) { chip ->
            val isSelected = selected == chip.value
            val (bg, fg) = if (isSelected) {
                MaterialTheme.colorScheme.primary to MaterialTheme.colorScheme.onPrimary
            } else {
                MaterialTheme.colorScheme.surfaceContainerLowest to MaterialTheme.colorScheme.onSurfaceVariant
            }
            Surface(
                shape = RoundedCornerShape(50),
                color = bg,
                border = BorderStroke(
                    1.dp,
                    if (isSelected) MaterialTheme.colorScheme.primary else OutlineVariant.copy(alpha = 0.6f),
                ),
                modifier = Modifier.clickable { onSelect(chip.value) },
            ) {
                Text(
                    text = chip.label,
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = fg,
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                )
            }
        }
    }
}

private data class FilterChipDef(val value: String?, val label: String)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SearchField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
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
            onValueChange = onValueChange,
            placeholder = {
                Text(
                    text = placeholder,
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
private fun InvoiceDetailPane(
    state: InvoicesUiState,
    onBack: () -> Unit,
    onEdit: () -> Unit,
    onIssueInvoice: () -> Unit,
    onViewPdf: () -> Unit,
    onGeneratePdf: () -> Unit,
    onDownloadPdf: () -> Unit,
    onPrintPdf: () -> Unit,
    onSharePdfToWhatsApp: () -> Unit,
    onIssueAndPreparePdf: (InvoicePdfAction) -> Unit,
    modifier: Modifier = Modifier,
) {
    val invoice = state.selectedInvoice

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            ToolbarRow(
                title = invoice?.invoiceNumber ?: if (invoice?.documentType == "quotation") "Presupuesto" else "Factura",
                onBack = onBack,
                trailing = {
                    if (invoice?.status == "draft") {
                        IconButton(onClick = onEdit, enabled = !state.isSaving) {
                            Icon(
                                imageVector = Icons.Outlined.Edit,
                                contentDescription = "Editar",
                                tint = MaterialTheme.colorScheme.primary,
                            )
                        }
                    }
                },
            )
        }

        if (state.isDetailLoading) {
            item { InlineLoader() }
        }

        state.errorMessage?.let { message ->
            item { ErrorBanner(message = message) }
        }

        if (invoice != null) {
            item { InvoiceHeaderCard(invoice = invoice) }
            item { InvoiceTotalsCard(invoice = invoice) }
            item {
                InvoiceActionRow(
                    invoice = invoice,
                    isSaving = state.isSaving,
                    onIssueInvoice = onIssueInvoice,
                    onViewPdf = onViewPdf,
                    onDownloadPdf = onDownloadPdf,
                    onPrintPdf = onPrintPdf,
                    onSharePdfToWhatsApp = onSharePdfToWhatsApp,
                    onIssueAndPreparePdf = onIssueAndPreparePdf,
                )
            }
            item {
                Text(
                    text = "Líneas (${invoice.items.size})",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
            }
            items(invoice.items, key = { it.id }) { item ->
                InvoiceLineCard(item = item, currencySymbol = invoice.currencySymbol)
            }
        }
    }
}

@Composable
private fun InvoiceActionRow(
    invoice: InvoiceDetail,
    isSaving: Boolean,
    onIssueInvoice: () -> Unit,
    onViewPdf: () -> Unit,
    onDownloadPdf: () -> Unit,
    onPrintPdf: () -> Unit,
    onSharePdfToWhatsApp: () -> Unit,
    onIssueAndPreparePdf: (InvoicePdfAction) -> Unit,
) {
    val documentName = if (invoice.documentType == "quotation") "presupuesto" else "factura"

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        if (invoice.status == "draft") {
            PrimaryButton(
                text = "Emitir $documentName y ver PDF",
                icon = Icons.Outlined.PictureAsPdf,
                isBusy = isSaving,
                onClick = { onIssueAndPreparePdf(InvoicePdfAction.View) },
            )
            SecondaryButton(
                text = "Solo emitir $documentName",
                icon = Icons.AutoMirrored.Outlined.Send,
                modifier = Modifier.fillMaxWidth(),
                enabled = !isSaving,
                onClick = onIssueInvoice,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                SecondaryButton(
                    text = "Emitir + Imprimir",
                    icon = Icons.Outlined.Print,
                    modifier = Modifier.weight(1f),
                    enabled = !isSaving,
                    onClick = { onIssueAndPreparePdf(InvoicePdfAction.Print) },
                )
                SecondaryButton(
                    text = "Emitir + WhatsApp",
                    icon = Icons.Outlined.Share,
                    modifier = Modifier.weight(1f),
                    enabled = !isSaving,
                    onClick = { onIssueAndPreparePdf(InvoicePdfAction.WhatsApp) },
                )
            }
        } else {
            PrimaryButton(
                text = "Ver PDF",
                icon = Icons.Outlined.PictureAsPdf,
                isBusy = isSaving,
                onClick = onViewPdf,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                SecondaryButton(
                    text = "Compartir",
                    icon = Icons.Outlined.Share,
                    modifier = Modifier.weight(1f),
                    enabled = !isSaving,
                    onClick = onDownloadPdf,
                )
                SecondaryButton(
                    text = "WhatsApp",
                    icon = Icons.Outlined.Share,
                    modifier = Modifier.weight(1f),
                    enabled = !isSaving,
                    onClick = onSharePdfToWhatsApp,
                )
            }
            SecondaryButton(
                text = "Imprimir",
                icon = Icons.Outlined.Print,
                modifier = Modifier.fillMaxWidth(),
                enabled = !isSaving,
                onClick = onPrintPdf,
            )
        }
    }
}

@Composable
private fun PrimaryButton(
    text: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector? = null,
    isBusy: Boolean = false,
    enabled: Boolean = true,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Button(
        onClick = onClick,
        modifier = modifier
            .fillMaxWidth()
            .height(50.dp),
        shape = RoundedCornerShape(14.dp),
        enabled = enabled && !isBusy,
        colors = ButtonDefaults.buttonColors(
            containerColor = MaterialTheme.colorScheme.primary,
            contentColor = MaterialTheme.colorScheme.onPrimary,
        ),
    ) {
        if (isBusy) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                strokeWidth = 2.dp,
                color = MaterialTheme.colorScheme.onPrimary,
            )
        } else {
            if (icon != null) {
                Icon(imageVector = icon, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(8.dp))
            }
            Text(
                text = text,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
private fun SecondaryButton(
    text: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector? = null,
    enabled: Boolean = true,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Surface(
        modifier = modifier
            .height(48.dp)
            .clickable(enabled = enabled, onClick = onClick),
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(14.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.7f)),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (icon != null) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(18.dp),
                )
                Spacer(Modifier.size(8.dp))
            }
            Text(
                text = text,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
        }
    }
}

@Composable
private fun ToolbarRow(
    title: String,
    onBack: () -> Unit,
    trailing: (@Composable () -> Unit)? = null,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconButton(onClick = onBack) {
            Icon(
                imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                contentDescription = "Volver",
                tint = MaterialTheme.colorScheme.onSurface,
            )
        }
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier
                .weight(1f)
                .padding(horizontal = 4.dp),
        )
        if (trailing != null) trailing()
    }
}

@Composable
private fun InvoicePreviewPane(
    state: InvoicesUiState,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    InvoiceHtmlPreviewPane(
        isPreviewLoading = state.isPreviewLoading,
        errorMessage = state.errorMessage,
        previewHtml = state.previewHtml,
        onBack = onBack,
        modifier = modifier,
    )
}

@Composable
private fun InvoiceHtmlPreviewPane(
    isPreviewLoading: Boolean,
    errorMessage: String?,
    previewHtml: String?,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp)
            .padding(top = 8.dp, bottom = 0.dp),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) {
                Icon(
                    imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                    contentDescription = "Volver",
                    tint = MaterialTheme.colorScheme.onSurface,
                )
            }
            Text(
                text = "Vista previa",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            previewHtml?.let {
                IconButton(onClick = { openHtmlExternally(context, it) }) {
                    Icon(
                        imageVector = Icons.Outlined.OpenInBrowser,
                        contentDescription = "Abrir en navegador",
                        tint = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }

        Spacer(Modifier.height(8.dp))

        Box(modifier = Modifier.weight(1f).fillMaxWidth()) {
            when {
                isPreviewLoading -> Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) { InlineLoader() }

                errorMessage != null -> Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    ErrorBanner(message = errorMessage)
                    Text(
                        text = "Si el problema persiste, abre la vista previa en el navegador (icono arriba).",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                previewHtml != null -> PreviewWebView(
                    html = previewHtml,
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(bottom = 96.dp),
                )

                else -> Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = "Sin contenido para mostrar.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}

@Composable
private fun PreviewWebView(html: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        color = MaterialTheme.colorScheme.surfaceContainerLowest,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, OutlineVariant.copy(alpha = 0.6f)),
    ) {
        AndroidView(
            modifier = Modifier.fillMaxSize(),
            factory = { context ->
                WebView(context).apply {
                    webViewClient = WebViewClient()
                    settings.javaScriptEnabled = true
                    settings.loadWithOverviewMode = true
                    settings.useWideViewPort = true
                    settings.builtInZoomControls = true
                    settings.displayZoomControls = false
                    settings.setSupportZoom(true)
                    setInitialScale(1)
                    setBackgroundColor(android.graphics.Color.WHITE)
                }
            },
            update = { webView ->
                webView.loadDataWithBaseURL(
                    "about:blank",
                    enhancePreviewHtml(html),
                    "text/html",
                    "UTF-8",
                    null,
                )
            },
        )
    }
}

private fun openHtmlExternally(context: Context, html: String) {
    val directory = File(context.cacheDir, "invoices").apply { mkdirs() }
    val file = File(directory, "preview.html").apply {
        writeText(enhancePreviewHtml(html), Charsets.UTF_8)
    }
    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    val intent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, "text/html")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
    }
    try {
        context.startActivity(Intent.createChooser(intent, "Abrir vista previa"))
    } catch (_: ActivityNotFoundException) {
        Toast.makeText(context, "No hay app para abrir HTML.", Toast.LENGTH_SHORT).show()
    }
}

/**
 * The PDF blade is A4-sized (210mm). On phones it renders huge by default —
 * we inject a viewport scaled to the document width so it fits the WebView
 * while still letting the user pinch to zoom.
 */
private fun enhancePreviewHtml(html: String): String {
    val viewport = "<meta name=\"viewport\" content=\"width=820, initial-scale=0.45, minimum-scale=0.3, maximum-scale=3.0, user-scalable=yes\">"
    val headMatch = Regex("(?i)<head[^>]*>").find(html)
    return if (headMatch != null) {
        val insertionPoint = headMatch.range.last + 1
        html.substring(0, insertionPoint) + viewport + html.substring(insertionPoint)
    } else {
        "<!DOCTYPE html><html><head>$viewport</head><body>$html</body></html>"
    }
}

@Composable
private fun DocumentTypeSelector(
    selectedType: String,
    onSelected: (String) -> Unit,
    enabled: Boolean,
    modifier: Modifier = Modifier,
) {
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Tipo de documento", style = MaterialTheme.typography.labelLarge)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (selectedType == "invoice") {
                Button(onClick = { onSelected("invoice") }, enabled = enabled, modifier = Modifier.weight(1f)) {
                    Text("Factura")
                }
            } else {
                OutlinedButton(onClick = { onSelected("invoice") }, enabled = enabled, modifier = Modifier.weight(1f)) {
                    Text("Factura")
                }
            }

            if (selectedType == "quotation") {
                Button(onClick = { onSelected("quotation") }, enabled = enabled, modifier = Modifier.weight(1f)) {
                    Text("Presupuesto")
                }
            } else {
                OutlinedButton(onClick = { onSelected("quotation") }, enabled = enabled, modifier = Modifier.weight(1f)) {
                    Text("Presupuesto")
                }
            }
        }
    }
}

@Composable
private fun InvoiceFormPane(
    title: String,
    clients: List<ClientRecord>,
    bootstrap: BootstrapCatalogs?,
    existingInvoice: InvoiceDetail?,
    isSaving: Boolean,
    isPreviewLoading: Boolean,
    previewHtml: String?,
    errorMessage: String?,
    onBack: () -> Unit,
    onSave: (InvoiceDraft) -> Unit,
    onPreview: (InvoiceDraft) -> Unit,
    onClearPreview: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val defaults = remember(bootstrap, existingInvoice, clients) {
        InvoiceFormDefaults.from(bootstrap = bootstrap, existingInvoice = existingInvoice, clients = clients)
    }
    var documentType by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.documentType) }
    var invoiceDate by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.invoiceDate) }
    var selectedClientId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.clientId) }
    var selectedTermId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.paymentTermId) }
    var selectedCurrencyId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.currencyId) }
    var selectedFiscalProfileId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.fiscalProfileId) }
    var selectedBankAccountId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.bankAccountId) }
    var selectedWarrantyId by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.warrantyId) }
    var warrantyText by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.warrantyText) }
    var legalText by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.legalText) }
    var conformityText by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.conformityText) }
    var observations by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.observations) }
    var preparedBy by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.preparedBy) }
    var receivedBy by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.receivedBy) }
    var amountReceived by rememberSaveable(existingInvoice?.id) { mutableStateOf(defaults.amountReceived) }
    val items = remember(existingInvoice?.id, bootstrap) {
        mutableStateListOf<EditableInvoiceItem>().apply {
            addAll(defaults.items)
        }
    }
    var showPreview by rememberSaveable(existingInvoice?.id) { mutableStateOf(false) }
    val canSubmit = selectedClientId != null && selectedTermId != null && selectedCurrencyId != null
            && selectedFiscalProfileId != null
            && selectedWarrantyId != null
            && items.all { it.description.isNotBlank() && it.quantity.isNotBlank() && it.unitCost.isNotBlank() }

    fun currentDraft(): InvoiceDraft? {
        val paymentTermId = selectedTermId ?: return null
        val clientId = selectedClientId ?: return null
        val currencyId = selectedCurrencyId ?: return null
        val fiscalProfileId = selectedFiscalProfileId ?: return null

        return InvoiceDraft(
            documentType = documentType,
            invoiceDate = invoiceDate,
            paymentTermId = paymentTermId,
            clientId = clientId,
            currencyId = currencyId,
            fiscalProfileId = fiscalProfileId,
            bankAccountId = selectedBankAccountId,
            warrantyId = selectedWarrantyId,
            warrantyText = warrantyText,
            legalText = legalText,
            conformityText = conformityText,
            observations = observations,
            amountReceived = if (documentType == "quotation") null else amountReceived,
            preparedBy = preparedBy,
            receivedBy = receivedBy,
            items = items.map {
                InvoiceDraftItem(
                    description = it.description,
                    quantity = it.quantity,
                    unitCost = it.unitCost,
                    taxId = it.taxId,
                )
            },
        )
    }

    if (showPreview) {
        InvoiceHtmlPreviewPane(
            isPreviewLoading = isPreviewLoading,
            errorMessage = errorMessage,
            previewHtml = previewHtml,
            onBack = {
                showPreview = false
                onClearPreview()
            },
            modifier = modifier,
        )
        return
    }

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
        contentPadding = PaddingValues(top = 12.dp, bottom = 96.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item { ToolbarRow(title = title, onBack = onBack) }

        if (bootstrap == null) {
            item {
                EmptyState(
                    title = "Falta configuración",
                    body = "La factura requiere cargar el bootstrap antes de editar campos maestros.",
                )
            }
            return@LazyColumn
        }

        if (clients.isEmpty()) {
            item {
                EmptyState(
                    title = "No hay clientes disponibles",
                    body = "Primero crea un cliente o revisa permisos del usuario actual.",
                )
            }
            return@LazyColumn
        }

        item {
            DocumentTypeSelector(
                selectedType = documentType,
                onSelected = { documentType = it },
                enabled = !isSaving,
            )
        }
        item {
            IsoDatePickerField(
                label = if (documentType == "quotation") "Fecha presupuesto" else "Fecha factura",
                value = invoiceDate,
                onDateSelected = { invoiceDate = it },
                modifier = Modifier.fillMaxWidth(),
                enabled = !isSaving,
            )
        }
        item {
            SelectorField(
                label = "Cliente",
                options = clients,
                selectedId = selectedClientId,
                optionLabel = { it.name },
                onSelected = { selectedClientId = it },
            )
        }
        item {
            SelectorField(
                label = "Termino de pago",
                options = bootstrap.paymentTerms,
                selectedId = selectedTermId,
                optionLabel = { it.name },
                onSelected = { selectedTermId = it },
            )
        }
        item {
            SelectorField(
                label = "Moneda",
                options = bootstrap.currencies,
                selectedId = selectedCurrencyId,
                optionLabel = { "${it.code} ${it.symbol}" },
                onSelected = { selectedCurrencyId = it },
            )
        }
        item {
            SelectorField(
                label = "Empresa",
                options = bootstrap.fiscalProfiles,
                selectedId = selectedFiscalProfileId,
                optionLabel = { it.name },
                onSelected = { selectedFiscalProfileId = it },
            )
        }
        item {
            SelectorField(
                label = "Cuenta bancaria",
                options = bootstrap.bankAccounts,
                selectedId = selectedBankAccountId,
                optionLabel = { "${if (it.accountType == "unofficial") "No oficial" else "Oficial"} - ${it.name}" },
                onSelected = { selectedBankAccountId = it },
                allowEmpty = true,
            )
        }
        item {
            SelectorField(
                label = "Garantia",
                options = bootstrap.warranties,
                selectedId = selectedWarrantyId,
                optionLabel = { it.title },
                onSelected = { selectedWarrantyId = it },
                allowEmpty = false,
            )
        }
        item {
            OutlinedTextField(
                value = warrantyText,
                onValueChange = { warrantyText = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Texto garantia") },
            )
        }
        item {
            OutlinedTextField(
                value = legalText,
                onValueChange = { legalText = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Texto legal") },
            )
        }
        item {
            OutlinedTextField(
                value = conformityText,
                onValueChange = { conformityText = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Texto de conformidad") },
            )
        }
        item {
            OutlinedTextField(
                value = observations,
                onValueChange = { observations = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Observaciones") },
            )
        }
        item {
            OutlinedTextField(
                value = preparedBy,
                onValueChange = { preparedBy = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Preparado por") },
                singleLine = true,
            )
        }
        item {
            OutlinedTextField(
                value = receivedBy,
                onValueChange = { receivedBy = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Recibido por") },
                singleLine = true,
            )
        }
        if (documentType != "quotation") {
            item {
                OutlinedTextField(
                    value = amountReceived,
                    onValueChange = { amountReceived = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Monto recibido") },
                    singleLine = true,
                )
            }
        }
        item {
            Text(
                text = "Lineas",
                style = MaterialTheme.typography.titleLarge,
            )
        }
        itemsIndexed(items) { index, item ->
            InvoiceItemEditorCard(
                item = item,
                taxes = bootstrap.taxes,
                canRemove = items.size > 1,
                onChanged = { updated -> items[index] = updated },
                onRemove = { items.removeAt(index) },
            )
        }
        item {
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(54.dp)
                    .clickable {
                        val fallbackTaxId = bootstrap.taxes.firstOrNull()?.id ?: return@clickable
                        items.add(
                            EditableInvoiceItem(
                                description = "",
                                quantity = "1",
                                unitCost = "",
                                taxId = fallbackTaxId,
                            ),
                        )
                    },
                color = MaterialTheme.colorScheme.primary.copy(alpha = 0.06f),
                shape = RoundedCornerShape(14.dp),
                border = BorderStroke(
                    1.5.dp,
                    MaterialTheme.colorScheme.primary.copy(alpha = 0.4f),
                ),
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 14.dp),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(
                        imageVector = Icons.Filled.Add,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                    )
                    Spacer(Modifier.size(8.dp))
                    Text(
                        text = "Agregar otra línea",
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }
        item {
            LocalPreviewTotalsCard(
                items = items,
                bootstrap = bootstrap,
                currencySymbol = bootstrap.currencies.firstOrNull { it.id == selectedCurrencyId }?.symbol ?: "",
            )
        }

        errorMessage?.let { message ->
            item {
                ErrorBanner(message = message)
            }
        }

        item {
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                SecondaryButton(
                    text = "Vista previa",
                    icon = Icons.Outlined.RemoveRedEye,
                    modifier = Modifier.weight(1f),
                    enabled = !isSaving && !isPreviewLoading && canSubmit,
                    onClick = {
                        val draft = currentDraft() ?: return@SecondaryButton
                        showPreview = true
                        onPreview(draft)
                    },
                )
                Button(
                    onClick = {
                        val draft = currentDraft() ?: return@Button
                        onSave(draft)
                    },
                    enabled = !isSaving && canSubmit,
                    modifier = Modifier
                        .weight(1f)
                        .height(48.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.primary,
                        contentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                ) {
                    ActionLabel(isBusy = isSaving, label = "Guardar")
                }
            }
        }
    }
}

@Composable
private fun InvoiceSummaryCard(
    invoice: InvoiceSummary,
    onClick: () -> Unit,
) {
    SectionCard(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = invoice.invoiceNumber ?: "Borrador #${invoice.id}",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                StatusBadge(
                    status = invoice.status,
                    dense = true,
                    label = invoiceDocumentStatusLabel(invoice.status, invoice.documentType),
                )
            }
            Spacer(Modifier.height(4.dp))
            Text(
                text = if (invoice.documentType == "quotation") "Presupuesto" else "Factura",
                style = MaterialTheme.typography.labelSmall,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = invoice.clientName,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.height(10.dp))
            HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))
            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Monto total",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            text = formatMoney(invoice.total, invoice.currencySymbol),
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.onSurface,
                        )
                    }
                }
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .background(
                            MaterialTheme.colorScheme.surfaceContainerLow,
                            RoundedCornerShape(10.dp),
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = if (invoice.pdfPath != null) Icons.Outlined.PictureAsPdf
                        else Icons.Outlined.Description,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(20.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun InvoiceHeaderCard(invoice: InvoiceDetail) {
    SectionCard {
        Column(modifier = Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                InitialAvatar(text = invoice.clientName, size = 44.dp)
                Spacer(Modifier.size(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = invoice.invoiceNumber ?: "Borrador #${invoice.id}",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = if (invoice.documentType == "quotation") "Presupuesto" else "Factura",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Text(
                        text = invoice.clientName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface,
                    )
                }
                StatusBadge(
                    status = invoice.status,
                    label = invoiceDocumentStatusLabel(invoice.status, invoice.documentType),
                )
            }
            Spacer(Modifier.size(14.dp))
            HorizontalDivider(color = OutlineVariant.copy(alpha = 0.4f))
            Spacer(Modifier.size(14.dp))
            Row(modifier = Modifier.fillMaxWidth()) {
                MetaItem(label = "Fecha", value = invoice.invoiceDate, modifier = Modifier.weight(1f))
                MetaItem(
                    label = "Vence",
                    value = invoice.dueDate ?: "—",
                    modifier = Modifier.weight(1f),
                )
                MetaItem(
                    label = "Moneda",
                    value = invoice.currencyCode,
                    modifier = Modifier.weight(1f),
                )
            }
        }
    }
}

@Composable
private fun MetaItem(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier) {
        Text(
            text = label.uppercase(),
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            fontWeight = FontWeight.SemiBold,
        )
        Spacer(Modifier.size(2.dp))
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
        )
    }
}

@Composable
private fun InvoiceTotalsCard(invoice: InvoiceDetail) {
    SectionCard {
        Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            SummaryRow("Subtotal", formatMoney(invoice.subtotal, invoice.currencySymbol))
            SummaryRow("Impuestos", formatMoney(invoice.taxTotal, invoice.currencySymbol))
            if (invoice.documentType != "quotation") {
                SummaryRow("Cobrado", formatMoney(invoice.amountReceived, invoice.currencySymbol))
            }
            HorizontalDivider(
                color = OutlineVariant.copy(alpha = 0.5f),
                modifier = Modifier.padding(vertical = 4.dp),
            )
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "Total",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = formatMoney(invoice.total, invoice.currencySymbol),
                    fontSize = 24.sp,
                    lineHeight = 30.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            val balance = invoice.balanceDue.toBigDecimalOrZero()
            if (invoice.documentType != "quotation" && balance > BigDecimal.ZERO) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(
                        text = "Balance pendiente",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.error,
                    )
                    Text(
                        text = formatMoney(invoice.balanceDue, invoice.currencySymbol),
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.error,
                    )
                }
            }
        }
    }
}

@Composable
private fun SummaryRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.onSurface,
        )
    }
}

@Composable
private fun InvoiceLineCard(
    item: com.facturador.facturapro.domain.model.InvoiceLine,
    currencySymbol: String,
) {
    SectionCard {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.Top) {
                Text(
                    text = item.description,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = formatMoney(item.lineTotal, currencySymbol),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                LineChip(label = "Cant. ${item.quantity}")
                Spacer(Modifier.size(8.dp))
                LineChip(label = "${formatMoney(item.unitCost, currencySymbol)} c/u")
                Spacer(Modifier.size(8.dp))
                LineChip(label = item.taxName ?: "Imp ${item.taxRate}%")
            }
        }
    }
}

@Composable
private fun LineChip(label: String) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceContainerLow,
        shape = RoundedCornerShape(50),
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
        )
    }
}

@Composable
private fun InvoiceItemEditorCard(
    item: EditableInvoiceItem,
    taxes: List<com.facturador.facturapro.domain.model.TaxCatalogItem>,
    canRemove: Boolean,
    onChanged: (EditableInvoiceItem) -> Unit,
    onRemove: () -> Unit,
) {
    SectionCard {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "Detalle de línea",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f),
                )
                if (canRemove) {
                    Text(
                        text = "Quitar",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.error,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier
                            .clickable(onClick = onRemove)
                            .padding(horizontal = 6.dp, vertical = 4.dp),
                    )
                }
            }
            OutlinedTextField(
                value = item.description,
                onValueChange = { onChanged(item.copy(description = it)) },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Descripción") },
                shape = RoundedCornerShape(12.dp),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(
                    value = item.quantity,
                    onValueChange = { onChanged(item.copy(quantity = it)) },
                    modifier = Modifier.weight(1f),
                    label = { Text("Cantidad") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                )
                OutlinedTextField(
                    value = item.unitCost,
                    onValueChange = { onChanged(item.copy(unitCost = it)) },
                    modifier = Modifier.weight(1f.coerceAtLeast(1.5f)),
                    label = { Text("Costo unitario") },
                    singleLine = true,
                    shape = RoundedCornerShape(12.dp),
                )
            }
            SelectorField(
                label = "Impuesto",
                options = taxes,
                selectedId = item.taxId,
                optionLabel = { "${it.name} (${it.rate}%)" },
                onSelected = { onChanged(item.copy(taxId = it ?: item.taxId)) },
            )
        }
    }
}

@Composable
private fun LocalPreviewTotalsCard(
    items: List<EditableInvoiceItem>,
    bootstrap: BootstrapCatalogs,
    currencySymbol: String,
) {
    val subtotal = items.sumOf { item ->
        item.quantity.toBigDecimalOrZero().multiply(item.unitCost.toBigDecimalOrZero())
    }
    val taxTotal = items.sumOf { item ->
        val rate = bootstrap.taxes.firstOrNull { it.id == item.taxId }?.rate?.toBigDecimalOrZero() ?: BigDecimal.ZERO
        item.quantity.toBigDecimalOrZero()
            .multiply(item.unitCost.toBigDecimalOrZero())
            .multiply(rate)
            .divide(BigDecimal("100"), 4, RoundingMode.HALF_UP)
    }
    val total = subtotal + taxTotal

    SectionCard {
        Column(modifier = Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(
                text = "Resumen estimado",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Text(
                text = "El backend recalcula el valor final al guardar.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(4.dp))
            SummaryRow("Subtotal", formatMoney(subtotal.toPlainString(), currencySymbol))
            SummaryRow("Impuestos", formatMoney(taxTotal.toPlainString(), currencySymbol))
            HorizontalDivider(color = OutlineVariant.copy(alpha = 0.5f))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "Total",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Text(
                    text = formatMoney(total.toPlainString(), currencySymbol),
                    fontSize = 22.sp,
                    lineHeight = 28.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T> SelectorField(
    label: String,
    options: List<T>,
    selectedId: Long?,
    optionLabel: (T) -> String,
    onSelected: (Long?) -> Unit,
    allowEmpty: Boolean = false,
) where T : Any {
    var expanded by remember { mutableStateOf(false) }
    val selectedOption = options.firstOrNull { option -> optionId(option) == selectedId }

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { expanded = !expanded },
    ) {
        OutlinedTextField(
            value = selectedOption?.let(optionLabel) ?: if (allowEmpty) "Sin seleccionar" else "",
            onValueChange = {},
            modifier = Modifier
                .menuAnchor(ExposedDropdownMenuAnchorType.PrimaryNotEditable, true)
                .fillMaxWidth(),
            readOnly = true,
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
        )
        ExposedDropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
        ) {
            if (allowEmpty) {
                DropdownMenuItem(
                    text = { Text("Sin seleccionar") },
                    onClick = {
                        expanded = false
                        onSelected(null)
                    },
                )
            }
            options.forEach { option ->
                DropdownMenuItem(
                    text = { Text(optionLabel(option)) },
                    onClick = {
                        expanded = false
                        onSelected(optionId(option))
                    },
                )
            }
        }
    }
}

private fun optionId(option: Any): Long = when (option) {
    is ClientRecord -> option.id
    is com.facturador.facturapro.domain.model.PaymentTermCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.CurrencyCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.NamedCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.WarrantyCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.TaxCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.BankAccountCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.LegalTextCatalogItem -> option.id
    else -> error("Unsupported option type ${option::class.java.name}")
}


@Composable
private fun ActionLabel(
    isBusy: Boolean,
    label: String,
) {
    if (isBusy) {
        CircularProgressIndicator(
            modifier = Modifier.height(18.dp),
            strokeWidth = 2.dp,
            color = MaterialTheme.colorScheme.onPrimary,
        )
    } else {
        Text(label, fontWeight = FontWeight.SemiBold)
    }
}

private fun sharePdf(context: Context, absolutePath: String) {
    val file = File(absolutePath)
    if (!file.exists()) {
        Toast.makeText(context, "No se encontro el PDF de la factura.", Toast.LENGTH_LONG).show()
        return
    }

    val uri = FileProvider.getUriForFile(
        context,
        "${context.packageName}.fileprovider",
        file,
    )

    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "application/pdf"
        putExtra(Intent.EXTRA_STREAM, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }

    runCatching {
        context.startActivity(Intent.createChooser(intent, "Compartir factura"))
    }.onFailure {
        Toast.makeText(context, "No hay aplicacion disponible para compartir el PDF.", Toast.LENGTH_LONG).show()
    }
}

private fun sharePdfToWhatsApp(context: Context, absolutePath: String) {
    val file = File(absolutePath)
    if (!file.exists()) {
        Toast.makeText(context, "No se encontro el PDF de la factura.", Toast.LENGTH_LONG).show()
        return
    }

    val uri = FileProvider.getUriForFile(
        context,
        "${context.packageName}.fileprovider",
        file,
    )

    val packages = listOf("com.whatsapp", "com.whatsapp.w4b")
    for (packageName in packages) {
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "application/pdf"
            setPackage(packageName)
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }

        try {
            context.grantUriPermission(packageName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
            context.startActivity(intent)
            return
        } catch (_: ActivityNotFoundException) {
            continue
        } catch (_: SecurityException) {
            continue
        }
    }

    Toast.makeText(context, "WhatsApp no esta instalado en este dispositivo.", Toast.LENGTH_LONG).show()
}

private fun printPdf(context: Context, absolutePath: String) {
    val file = File(absolutePath)
    if (!file.exists()) {
        Toast.makeText(context, "No se encontro el PDF de la factura.", Toast.LENGTH_LONG).show()
        return
    }

    val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
    if (printManager == null) {
        Toast.makeText(context, "El servicio de impresion no esta disponible.", Toast.LENGTH_LONG).show()
        return
    }

    val attributes = PrintAttributes.Builder()
        .setMediaSize(PrintAttributes.MediaSize.ISO_A4)
        .setColorMode(PrintAttributes.COLOR_MODE_COLOR)
        .build()

    printManager.print(
        file.nameWithoutExtension,
        PdfPrintDocumentAdapter(file = file, jobName = file.name),
        attributes,
    )
}

private fun viewPdf(context: Context, absolutePath: String) {
    try {
        val file = File(absolutePath)

        if (!file.exists()) {
            Toast.makeText(
                context,
                "No se encontró el PDF local: $absolutePath",
                Toast.LENGTH_LONG
            ).show()
            return
        }

        if (file.length() <= 0L) {
            Toast.makeText(
                context,
                "El PDF existe, pero está vacío: ${file.absolutePath}",
                Toast.LENGTH_LONG
            ).show()
            return
        }

        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )

        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, "application/pdf")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }

        val canOpenPdf = viewIntent.resolveActivity(context.packageManager) != null

        if (canOpenPdf) {
            context.startActivity(
                Intent.createChooser(viewIntent, "Abrir factura PDF").apply {
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                }
            )
        } else {
            Toast.makeText(
                context,
                "No tienes una aplicación instalada para ver PDF. Usa Compartir o instala un lector PDF.",
                Toast.LENGTH_LONG
            ).show()

            sharePdf(context = context, absolutePath = absolutePath)
        }
    } catch (e: SecurityException) {
        Toast.makeText(
            context,
            "Error de permisos al abrir PDF: ${e.message}",
            Toast.LENGTH_LONG
        ).show()
    } catch (e: IllegalArgumentException) {
        Toast.makeText(
            context,
            "Error FileProvider: ${e.message}",
            Toast.LENGTH_LONG
        ).show()
    } catch (e: Exception) {
        Toast.makeText(
            context,
            "Error real al abrir PDF: ${e.message ?: e::class.java.simpleName}",
            Toast.LENGTH_LONG
        ).show()
    }
}

private enum class InvoicePane {
    List,
    Detail,
    Preview,
    Create,
    Edit,
    PdfViewer,
}
private data class EditableInvoiceItem(
    val description: String,
    val quantity: String,
    val unitCost: String,
    val taxId: Long,
)

private data class InvoiceFormDefaults(
    val documentType: String,
    val invoiceDate: String,
    val clientId: Long?,
    val paymentTermId: Long?,
    val currencyId: Long?,
    val fiscalProfileId: Long?,
    val bankAccountId: Long?,
    val warrantyId: Long?,
    val warrantyText: String,
    val legalText: String,
    val conformityText: String,
    val observations: String,
    val preparedBy: String,
    val receivedBy: String,
    val amountReceived: String,
    val items: List<EditableInvoiceItem>,
) {
    companion object {
        fun from(
            bootstrap: BootstrapCatalogs?,
            existingInvoice: InvoiceDetail?,
            clients: List<ClientRecord>,
        ): InvoiceFormDefaults {
            if (existingInvoice != null) {
                return InvoiceFormDefaults(
                    documentType = existingInvoice.documentType,
                    invoiceDate = existingInvoice.invoiceDate,
                    clientId = existingInvoice.clientId,
                    paymentTermId = existingInvoice.paymentTermId,
                    currencyId = existingInvoice.currencyId,
                    fiscalProfileId = existingInvoice.fiscalProfileId,
                    bankAccountId = existingInvoice.bankAccountId,
                    warrantyId = existingInvoice.warrantyId,
                    warrantyText = existingInvoice.warrantyText.orEmpty(),
                    legalText = existingInvoice.legalText.orEmpty(),
                    conformityText = existingInvoice.conformityText.orEmpty(),
                    observations = existingInvoice.observations.orEmpty(),
                    preparedBy = existingInvoice.preparedBy.orEmpty(),
                    receivedBy = existingInvoice.receivedBy.orEmpty(),
                    amountReceived = existingInvoice.amountReceived,
                    items = existingInvoice.items.map {
                        EditableInvoiceItem(
                            description = it.description,
                            quantity = it.quantity,
                            unitCost = it.unitCost,
                            taxId = it.taxId,
                        )
                    },
                )
            }

            val legalText = bootstrap?.legalTexts?.firstOrNull { it.isDefault } ?: bootstrap?.legalTexts?.firstOrNull()
            val warranty = bootstrap?.warranties?.firstOrNull { it.isDefault } ?: bootstrap?.warranties?.firstOrNull()
            val taxId = bootstrap?.taxes?.firstOrNull { it.isDefault }?.id ?: bootstrap?.taxes?.firstOrNull()?.id ?: 0L

            return InvoiceFormDefaults(
                documentType = "invoice",
                invoiceDate = LocalDate.now().toString(),
                clientId = clients.firstOrNull()?.id,
                paymentTermId = bootstrap?.paymentTerms?.firstOrNull { it.isDefault }?.id ?: bootstrap?.paymentTerms?.firstOrNull()?.id,
                currencyId = bootstrap?.currencies?.firstOrNull { it.isDefault }?.id ?: bootstrap?.currencies?.firstOrNull()?.id,
                fiscalProfileId = bootstrap?.fiscalProfiles?.firstOrNull { it.isDefault }?.id ?: bootstrap?.fiscalProfiles?.firstOrNull()?.id,
                bankAccountId = bootstrap?.bankAccounts?.firstOrNull { it.isDefault }?.id ?: bootstrap?.bankAccounts?.firstOrNull()?.id,
                warrantyId = warranty?.id,
                warrantyText = legalText?.warrantyText ?: warranty?.title.orEmpty(),
                legalText = legalText?.legalFooter.orEmpty(),
                conformityText = legalText?.conformityText ?: "CONFORMIDAD DEL CLIENTE",
                observations = "",
                preparedBy = "",
                receivedBy = "",
                amountReceived = "0",
                items = listOf(
                    EditableInvoiceItem(
                        description = "",
                        quantity = "1",
                        unitCost = "",
                        taxId = taxId,
                    ),
                ),
            )
        }
    }
}

private fun String.toBigDecimalOrZero(): BigDecimal = runCatching {
    trim().ifBlank { "0" }.toBigDecimal()
}.getOrDefault(BigDecimal.ZERO)
