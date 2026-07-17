package com.facturador.facturapro.ui.technicalreports

import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.PictureAsPdf
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.RemoveRedEye
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Button
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
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.ClientRecord
import com.facturador.facturapro.domain.model.FiscalProfileLogoCatalogItem
import com.facturador.facturapro.domain.model.NamedCatalogItem
import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportDraft
import com.facturador.facturapro.domain.model.TechnicalReportSetting
import com.facturador.facturapro.domain.model.TechnicalReportSummary
import com.facturador.facturapro.ui.common.IsoDatePickerField
import com.facturador.facturapro.ui.invoices.PdfViewerScreen
import androidx.activity.compose.BackHandler
import java.time.LocalDate

@Composable
fun TechnicalReportsScreen(
    state: TechnicalReportsUiState,
    clients: List<ClientRecord>,
    bootstrap: BootstrapCatalogs?,
    openCreateRequest: Int,
    onSearchChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onSelectReport: (Long) -> Unit,
    onClearSelection: () -> Unit,
    onCreateReport: (TechnicalReportDraft) -> Unit,
    onUpdateReport: (Long, TechnicalReportDraft) -> Unit,
    onDeleteOrCancelReport: () -> Unit,
    onLoadPreview: () -> Unit,
    onClearPreview: () -> Unit,
    onGenerateAndViewPdf: () -> Unit,
    onClearInternalPdf: () -> Unit,
    onConsumeSavedEvent: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var showForm by remember { mutableStateOf(false) }
    var editingReport by remember { mutableStateOf<TechnicalReportDetail?>(null) }

    val hasBackAction = state.internalPdfPath != null ||
            state.previewHtml != null ||
            state.isPreviewLoading ||
            showForm ||
            state.selectedReport != null

    BackHandler(enabled = hasBackAction) {
        when {
            state.internalPdfPath != null -> onClearInternalPdf()
            state.previewHtml != null || state.isPreviewLoading -> onClearPreview()
            showForm -> {
                showForm = false
                editingReport = null
            }
            state.selectedReport != null -> onClearSelection()
        }
    }

    LaunchedEffect(openCreateRequest) {
        if (openCreateRequest > 0) {
            editingReport = null
            showForm = true
            onClearSelection()
        }
    }

    LaunchedEffect(state.savedReportId) {
        if (state.savedReportId != null) {
            showForm = false
            editingReport = null
            onConsumeSavedEvent()
        }
    }

    when {
        state.internalPdfPath != null -> PdfViewerScreen(
            filePath = state.internalPdfPath,
            onBack = onClearInternalPdf,
            modifier = modifier,
        )

        state.previewHtml != null || state.isPreviewLoading -> ReportPreviewPane(
            isLoading = state.isPreviewLoading,
            html = state.previewHtml,
            errorMessage = state.errorMessage,
            onBack = onClearPreview,
            modifier = modifier,
        )

        showForm -> TechnicalReportFormPane(
            clients = clients,
            bootstrap = bootstrap,
            setting = state.setting,
            existingReport = editingReport,
            isSaving = state.isSaving,
            errorMessage = state.errorMessage,
            onBack = {
                showForm = false
                editingReport = null
            },
            onSave = { draft ->
                val current = editingReport
                if (current == null) {
                    onCreateReport(draft)
                } else {
                    onUpdateReport(current.id, draft)
                }
            },
            modifier = modifier,
        )

        state.selectedReport != null -> TechnicalReportDetailPane(
            report = state.selectedReport,
            isSaving = state.isSaving,
            errorMessage = state.errorMessage,
            onBack = onClearSelection,
            onEdit = {
                editingReport = state.selectedReport
                showForm = true
            },
            onPreview = onLoadPreview,
            onGenerateAndViewPdf = onGenerateAndViewPdf,
            onDeleteOrCancel = onDeleteOrCancelReport,
            modifier = modifier,
        )

        else -> TechnicalReportsListPane(
            reports = state.reports,
            searchQuery = state.searchQuery,
            isLoading = state.isLoading,
            errorMessage = state.errorMessage,
            onSearchChanged = onSearchChanged,
            onRefresh = onRefresh,
            onNewReport = {
                editingReport = null
                showForm = true
            },
            onSelectReport = onSelectReport,
            modifier = modifier,
        )
    }
}

@Composable
private fun TechnicalReportsListPane(
    reports: List<TechnicalReportSummary>,
    searchQuery: String,
    isLoading: Boolean,
    errorMessage: String?,
    onSearchChanged: (String) -> Unit,
    onRefresh: () -> Unit,
    onNewReport: () -> Unit,
    onSelectReport: (Long) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
    ) {
        ScreenHeader(
            title = "Informes",
            trailing = {
                IconButton(onClick = onRefresh) {
                    Icon(Icons.Outlined.Refresh, contentDescription = "Actualizar")
                }
                IconButton(onClick = onNewReport) {
                    Icon(Icons.Filled.Add, contentDescription = "Nuevo informe")
                }
            },
        )

        OutlinedTextField(
            value = searchQuery,
            onValueChange = onSearchChanged,
            modifier = Modifier.fillMaxWidth(),
            leadingIcon = { Icon(Icons.Outlined.Search, contentDescription = null) },
            label = { Text("Buscar informe o destinatario") },
            singleLine = true,
        )

        Spacer(Modifier.height(12.dp))
        errorMessage?.let { ErrorBanner(it) }

        when {
            isLoading -> InlineLoader(Modifier.fillMaxSize())
            reports.isEmpty() -> EmptyState(text = "No hay informes tecnicos.")
            else -> LazyColumn(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                items(reports, key = { it.id }) { report ->
                    ReportListItem(report = report, onClick = { onSelectReport(report.id) })
                }
            }
        }
    }
}

@Composable
private fun ReportListItem(report: TechnicalReportSummary, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLowest),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        shape = RoundedCornerShape(12.dp),
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(report.reportNumber, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text(report.recipientName, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                StatusBadge(report.statusLabel, report.status)
            }
            Text(report.recipientAddress, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(report.reportDate, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
        }
    }
}

@Composable
private fun TechnicalReportDetailPane(
    report: TechnicalReportDetail,
    isSaving: Boolean,
    errorMessage: String?,
    onBack: () -> Unit,
    onEdit: () -> Unit,
    onPreview: () -> Unit,
    onGenerateAndViewPdf: () -> Unit,
    onDeleteOrCancel: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
    ) {
        ScreenHeader(
            title = report.reportNumber,
            navigation = onBack,
            trailing = {
                IconButton(onClick = onEdit, enabled = report.status == "draft") {
                    Icon(Icons.Outlined.Edit, contentDescription = "Editar")
                }
                IconButton(onClick = onPreview) {
                    Icon(Icons.Outlined.RemoveRedEye, contentDescription = "Vista previa")
                }
                IconButton(onClick = onGenerateAndViewPdf, enabled = report.status != "cancelled" && !isSaving) {
                    Icon(Icons.Outlined.PictureAsPdf, contentDescription = "Generar PDF")
                }
            },
        )

        errorMessage?.let { ErrorBanner(it) }

        LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLowest),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("INFORME", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                            StatusBadge(report.statusLabel, report.status)
                        }
                        Text("Fecha: ${report.reportDate}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("Destinatario: ${report.recipientName}", fontWeight = FontWeight.SemiBold)
                        report.recipientTaxId?.takeIf { it.isNotBlank() }?.let {
                            Text("Identificacion: $it", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Text(report.recipientAddress, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        HorizontalDivider()
                        Text(report.sellerName, fontWeight = FontWeight.SemiBold)
                        report.verificationCode?.takeIf { it.isNotBlank() }?.let { code ->
                            HorizontalDivider()
                            Text("Codigo de seguridad", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text(code, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
                        }
                        Text(listOfNotNull(report.sellerTaxId, report.sellerAddress, report.sellerCity).joinToString(" · "), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }

            report.introText?.takeIf { it.isNotBlank() }?.let { intro ->
                item { Text(intro, style = MaterialTheme.typography.bodyMedium) }
            }

            items(report.sections()) { section ->
                SectionPreviewCard(title = section.first, content = section.second.orEmpty().ifBlank { "Sin contenido." })
            }

            report.finalText?.takeIf { it.isNotBlank() }?.let { finalText ->
                item { Text(finalText, style = MaterialTheme.typography.bodyMedium) }
            }

            item {
                OutlinedButton(
                    onClick = onDeleteOrCancel,
                    enabled = !isSaving,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Icon(Icons.Outlined.Delete, contentDescription = null)
                    Spacer(Modifier.height(1.dp))
                    Text(if (report.status == "draft") "Eliminar informe" else "Anular informe")
                }
            }
        }
    }
}

@Composable
private fun TechnicalReportFormPane(
    clients: List<ClientRecord>,
    bootstrap: BootstrapCatalogs?,
    setting: TechnicalReportSetting?,
    existingReport: TechnicalReportDetail?,
    isSaving: Boolean,
    errorMessage: String?,
    onBack: () -> Unit,
    onSave: (TechnicalReportDraft) -> Unit,
    modifier: Modifier = Modifier,
) {
    val fiscalProfiles = bootstrap?.fiscalProfiles.orEmpty()
    val defaultProfile = fiscalProfiles.firstOrNull { it.isDefault } ?: fiscalProfiles.firstOrNull()

    var reportNumber by remember(existingReport?.id, setting) {
        mutableStateOf(existingReport?.reportNumber.orEmpty())
    }
    var reportDate by remember(existingReport?.id) {
        mutableStateOf(existingReport?.reportDate ?: LocalDate.now().toString())
    }
    var status by remember(existingReport?.id) {
        mutableStateOf(existingReport?.status ?: "draft")
    }
    var fiscalProfileId by remember(existingReport?.id, defaultProfile) {
        mutableStateOf(existingReport?.fiscalProfileId ?: defaultProfile?.id)
    }
    var logoPath by remember(existingReport?.id, defaultProfile) {
        mutableStateOf(existingReport?.logoPath)
    }
    var clientId by remember(existingReport?.id) {
        mutableStateOf(existingReport?.clientId)
    }
    var recipientName by remember(existingReport?.id) {
        mutableStateOf(existingReport?.recipientName.orEmpty())
    }
    var recipientTaxId by remember(existingReport?.id) {
        mutableStateOf(existingReport?.recipientTaxId.orEmpty())
    }
    var recipientAddress by remember(existingReport?.id) {
        mutableStateOf(existingReport?.recipientAddress.orEmpty())
    }
    var section1Title by remember(existingReport?.id, setting) {
        mutableStateOf(existingReport?.section1Title ?: setting?.section1DefaultTitle.orEmpty())
    }
    var section1Content by remember(existingReport?.id) { mutableStateOf(existingReport?.section1Content.orEmpty()) }
    var section2Title by remember(existingReport?.id, setting) {
        mutableStateOf(existingReport?.section2Title.orEmpty())
    }
    var section2Content by remember(existingReport?.id) { mutableStateOf(existingReport?.section2Content.orEmpty()) }
    var section3Title by remember(existingReport?.id, setting) {
        mutableStateOf(existingReport?.section3Title.orEmpty())
    }
    var section3Content by remember(existingReport?.id) { mutableStateOf(existingReport?.section3Content.orEmpty()) }
    var section4Title by remember(existingReport?.id, setting) {
        mutableStateOf(existingReport?.section4Title.orEmpty())
    }
    var section4Content by remember(existingReport?.id) { mutableStateOf(existingReport?.section4Content.orEmpty()) }
    val selectedProfile = fiscalProfiles.firstOrNull { it.id == fiscalProfileId }
    val availableLogos = selectedProfile?.logos.orEmpty()

    LaunchedEffect(fiscalProfileId, bootstrap) {
        val profile = fiscalProfiles.firstOrNull { it.id == fiscalProfileId }
        val profileLogos = profile?.logos.orEmpty()
        val currentStillValid = logoPath != null && profileLogos.any { it.path == logoPath }

        if (!currentStillValid) {
            logoPath = profileLogos.firstOrNull { it.isDefault }?.path
                ?: profileLogos.firstOrNull()?.path
                ?: profile?.logoPath
        }
    }

    val canSave = fiscalProfileId != null &&
        recipientName.isNotBlank() &&
        recipientAddress.isNotBlank() &&
        section1Title.isNotBlank() &&
        !isSaving

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
    ) {
        ScreenHeader(
            title = if (existingReport == null) "Nuevo informe" else "Editar informe",
            navigation = onBack,
        )
        errorMessage?.let { ErrorBanner(it) }

        LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                FormCard(title = "Documento") {
                    OutlinedTextField(
                        value = reportNumber,
                        onValueChange = { reportNumber = it },
                        enabled = setting?.allowManualNumber == true,
                        modifier = Modifier.fillMaxWidth(),
                        label = { Text("Numero") },
                        placeholder = { Text(setting?.nextNumberPreview ?: "Automatico") },
                        singleLine = true,
                    )
                    IsoDatePickerField(
                        label = "Fecha",
                        value = reportDate,
                        onDateSelected = { reportDate = it },
                    )
                    SelectorField(
                        label = "Estado",
                        options = statusOptions,
                        selectedId = status,
                        optionLabel = { it.second },
                        onSelected = { status = it ?: "draft" },
                    )
                }
            }

            item {
                FormCard(title = "Emisor") {
                    SelectorField(
                        label = "Perfil fiscal",
                        options = fiscalProfiles,
                        selectedId = fiscalProfileId,
                        optionLabel = { it.name },
                        onSelected = {
                            fiscalProfileId = it
                            logoPath = null
                        },
                    )
                    if (availableLogos.isNotEmpty()) {
                        LogoSelectorField(
                            label = "Logo del informe",
                            options = availableLogos,
                            selectedPath = logoPath,
                            onSelected = { logoPath = it },
                            allowEmpty = true,
                        )
                    }
                }
            }

            item {
                FormCard(title = "Destinatario") {
                    SelectorField(
                        label = "Cliente",
                        options = clients,
                        selectedId = clientId,
                        optionLabel = { it.name },
                        allowEmpty = true,
                        onSelected = { selected ->
                            clientId = selected
                            clients.firstOrNull { it.id == selected }?.let { client ->
                                recipientName = client.name
                                recipientTaxId = client.taxId.orEmpty()
                                recipientAddress = listOfNotNull(client.address, client.city).joinToString(" ")
                            }
                        },
                    )
                    OutlinedTextField(value = recipientName, onValueChange = { recipientName = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Destinatario") })
                    OutlinedTextField(value = recipientTaxId, onValueChange = { recipientTaxId = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Identificacion fiscal") })
                    OutlinedTextField(value = recipientAddress, onValueChange = { recipientAddress = it }, modifier = Modifier.fillMaxWidth(), label = { Text("Direccion") }, minLines = 2)
                }
            }

            item { SectionEditorCard(1, section1Title, section1Content, { section1Title = it }, { section1Content = it }) }
            item { SectionEditorCard(2, section2Title, section2Content, { section2Title = it }, { section2Content = it }) }
            item { SectionEditorCard(3, section3Title, section3Content, { section3Title = it }, { section3Content = it }) }
            item { SectionEditorCard(4, section4Title, section4Content, { section4Title = it }, { section4Content = it }) }

            item {
                Button(
                    onClick = {
                        onSave(
                            TechnicalReportDraft(
                                reportNumber = reportNumber.takeIf { setting?.allowManualNumber == true },
                                reportDate = reportDate,
                                fiscalProfileId = requireNotNull(fiscalProfileId),
                                logoPath = logoPath,
                                clientId = clientId,
                                recipientName = recipientName,
                                recipientTaxId = recipientTaxId,
                                recipientAddress = recipientAddress,
                                section1Title = section1Title,
                                section1Content = section1Content,
                                section2Title = section2Title,
                                section2Content = section2Content,
                                section3Title = section3Title,
                                section3Content = section3Content,
                                section4Title = section4Title,
                                section4Content = section4Content,
                                introText = null,
                                finalText = null,
                                notes = null,
                                status = status,
                            ),
                        )
                    },
                    enabled = canSave,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(if (isSaving) "Guardando..." else "Guardar informe")
                }
            }
        }
    }
}

@Composable
private fun SectionEditorCard(
    number: Int,
    title: String,
    content: String,
    onTitleChanged: (String) -> Unit,
    onContentChanged: (String) -> Unit,
) {
    FormCard(title = "Seccion $number") {
        OutlinedTextField(value = title, onValueChange = onTitleChanged, modifier = Modifier.fillMaxWidth(), label = { Text("Titulo") })
        OutlinedTextField(value = content, onValueChange = onContentChanged, modifier = Modifier.fillMaxWidth(), label = { Text("Contenido") }, minLines = 5)
    }
}

@Composable
private fun SectionPreviewCard(title: String, content: String) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLowest),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        shape = RoundedCornerShape(12.dp),
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text(content, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun FormCard(title: String, content: @Composable ColumnScope.() -> Unit) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLowest),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        shape = RoundedCornerShape(12.dp),
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            content()
        }
    }
}

@Composable
private fun ReportPreviewPane(
    isLoading: Boolean,
    html: String?,
    errorMessage: String?,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
    ) {
        ScreenHeader(title = "Vista previa", navigation = onBack)
        when {
            isLoading -> InlineLoader(Modifier.fillMaxSize())
            errorMessage != null -> ErrorBanner(errorMessage)
            html != null -> PreviewWebView(html = html, modifier = Modifier.fillMaxSize())
            else -> EmptyState("Sin contenido para mostrar.")
        }
    }
}

@Composable
private fun PreviewWebView(html: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        color = Color.White,
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
    ) {
        AndroidView(
            modifier = Modifier.fillMaxSize(),
            factory = { context ->
                WebView(context).apply {
                    webViewClient = WebViewClient()
                    settings.javaScriptEnabled = false
                    settings.loadWithOverviewMode = true
                    settings.useWideViewPort = true
                    settings.builtInZoomControls = true
                    settings.displayZoomControls = false
                    setBackgroundColor(android.graphics.Color.WHITE)
                }
            },
            update = { webView ->
                webView.loadDataWithBaseURL("about:blank", enhancePreviewHtml(html), "text/html", "UTF-8", null)
            },
        )
    }
}

@Composable
private fun ScreenHeader(
    title: String,
    navigation: (() -> Unit)? = null,
    trailing: (@Composable RowScope.() -> Unit)? = null,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 8.dp, bottom = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (navigation != null) {
            IconButton(onClick = navigation) {
                Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Volver")
            }
        }
        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.weight(1f),
        )
        trailing?.invoke(this)
    }
}

@Composable
private fun InlineLoader(modifier: Modifier = Modifier) {
    Box(modifier = modifier, contentAlignment = Alignment.Center) {
        CircularProgressIndicator()
    }
}

@Composable
private fun ErrorBanner(message: String) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(bottom = 10.dp),
        color = MaterialTheme.colorScheme.errorContainer,
        contentColor = MaterialTheme.colorScheme.onErrorContainer,
        shape = RoundedCornerShape(10.dp),
    ) {
        Text(message, modifier = Modifier.padding(12.dp), style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun EmptyState(text: String) {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Text(text, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun StatusBadge(label: String, status: String) {
    val color = when (status) {
        "issued" -> MaterialTheme.colorScheme.primaryContainer
        "cancelled" -> MaterialTheme.colorScheme.errorContainer
        else -> MaterialTheme.colorScheme.surfaceVariant
    }
    Surface(color = color, shape = RoundedCornerShape(999.dp)) {
        Text(
            text = label,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.SemiBold,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T : Any> SelectorField(
    label: String,
    options: List<T>,
    selectedId: Long?,
    optionLabel: (T) -> String,
    onSelected: (Long?) -> Unit,
    allowEmpty: Boolean = false,
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedOption = options.firstOrNull { option -> optionId(option) == selectedId }

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = !expanded }) {
        OutlinedTextField(
            value = selectedOption?.let(optionLabel) ?: if (allowEmpty) "Sin seleccionar" else "",
            onValueChange = {},
            readOnly = true,
            modifier = Modifier
                .menuAnchor(ExposedDropdownMenuAnchorType.PrimaryNotEditable, true)
                .fillMaxWidth(),
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SelectorField(
    label: String,
    options: List<Pair<String, String>>,
    selectedId: String,
    optionLabel: (Pair<String, String>) -> String,
    onSelected: (String?) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedOption = options.firstOrNull { it.first == selectedId }

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = !expanded }) {
        OutlinedTextField(
            value = selectedOption?.let(optionLabel).orEmpty(),
            onValueChange = {},
            readOnly = true,
            modifier = Modifier
                .menuAnchor(ExposedDropdownMenuAnchorType.PrimaryNotEditable, true)
                .fillMaxWidth(),
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            options.forEach { option ->
                DropdownMenuItem(
                    text = { Text(optionLabel(option)) },
                    onClick = {
                        expanded = false
                        onSelected(option.first)
                    },
                )
            }
        }
    }
}

private fun optionId(option: Any): Long = when (option) {
    is ClientRecord -> option.id
    is NamedCatalogItem -> option.id
    is com.facturador.facturapro.domain.model.FiscalProfileCatalogItem -> option.id
    else -> error("Unsupported option type ${option::class.java.name}")
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun LogoSelectorField(
    label: String,
    options: List<FiscalProfileLogoCatalogItem>,
    selectedPath: String?,
    onSelected: (String?) -> Unit,
    allowEmpty: Boolean = false,
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedOption = options.firstOrNull { it.path == selectedPath }

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = !expanded }) {
        OutlinedTextField(
            value = selectedOption?.label ?: if (allowEmpty) "Logo del perfil" else "",
            onValueChange = {},
            readOnly = true,
            modifier = Modifier
                .menuAnchor(ExposedDropdownMenuAnchorType.PrimaryNotEditable, true)
                .fillMaxWidth(),
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
            colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            if (allowEmpty) {
                DropdownMenuItem(
                    text = { Text("Logo del perfil") },
                    onClick = {
                        expanded = false
                        onSelected(null)
                    },
                )
            }
            options.forEach { option ->
                DropdownMenuItem(
                    text = { Text(option.label) },
                    onClick = {
                        expanded = false
                        onSelected(option.path)
                    },
                )
            }
        }
    }
}

private fun TechnicalReportDetail.sections(): List<Pair<String, String?>> = listOf(
    section1Title to section1Content,
    section2Title to section2Content,
    section3Title to section3Content,
    section4Title to section4Content,
)

private val statusOptions = listOf(
    "draft" to "Borrador",
    "issued" to "Emitido",
    "cancelled" to "Anulado",
)

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
