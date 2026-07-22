package com.facturador.facturapro.ui.workspace

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.outlined.Assignment
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.People
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.CenterAlignedTopAppBar
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.facturador.facturapro.di.AppContainer
import com.facturador.facturapro.ui.auth.LoginUiState
import com.facturador.facturapro.ui.calendar.CalendarScreen
import com.facturador.facturapro.ui.calendar.CalendarViewModel
import com.facturador.facturapro.ui.clients.ClientsScreen
import com.facturador.facturapro.ui.clients.ClientsViewModel
import com.facturador.facturapro.ui.dashboard.DashboardScreen
import com.facturador.facturapro.ui.dashboard.DashboardViewModel
import com.facturador.facturapro.ui.invoices.InvoicesScreen
import com.facturador.facturapro.ui.invoices.InvoicesViewModel
import com.facturador.facturapro.ui.reports.ReportsScreen
import com.facturador.facturapro.ui.reports.ReportsViewModel
import com.facturador.facturapro.ui.settings.SettingsScreen
import com.facturador.facturapro.ui.settings.SettingsViewModel
import com.facturador.facturapro.ui.technicalreports.TechnicalReportsScreen
import com.facturador.facturapro.ui.technicalreports.TechnicalReportsViewModel
import com.facturador.facturapro.ui.verification.VerificationScreen
import com.facturador.facturapro.ui.verification.VerificationViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WorkspaceScreen(
    loginState: LoginUiState,
    container: AppContainer,
    onRetryBootstrap: () -> Unit,
    onLogout: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var section by rememberSaveable { mutableStateOf(WorkspaceSection.Home) }
    var newInvoiceRequest by rememberSaveable { mutableStateOf(0) }
    var newTechnicalReportRequest by rememberSaveable { mutableStateOf(0) }
    var openInvoiceRequest by rememberSaveable { mutableStateOf(0) }
    var openInvoiceId by rememberSaveable { mutableStateOf<Long?>(null) }

    val calendarViewModel: CalendarViewModel = viewModel(
        factory = CalendarViewModel.factory(container.calendarRepository),
    )
    val clientsViewModel: ClientsViewModel = viewModel(
        factory = ClientsViewModel.factory(container.clientRepository),
    )
    val invoicesViewModel: InvoicesViewModel = viewModel(
        factory = InvoicesViewModel.factory(container.invoiceRepository),
    )
    val reportsViewModel: ReportsViewModel = viewModel(
        factory = ReportsViewModel.factory(container.reportRepository),
    )
    val technicalReportsViewModel: TechnicalReportsViewModel = viewModel(
        factory = TechnicalReportsViewModel.factory(container.technicalReportRepository),
    )
    val settingsViewModel: SettingsViewModel = viewModel(
        factory = SettingsViewModel.factory(
            printerRepository = container.printerRepository,
            serverConfigStore = container.serverConfigStore,
        ),
    )
    val verificationViewModel: VerificationViewModel = viewModel(
        factory = VerificationViewModel.factory(container.invoiceRepository),
    )
    val dashboardViewModel: DashboardViewModel = viewModel(
        factory = DashboardViewModel.factory(container.dashboardRepository),
    )
    val clientsState by clientsViewModel.uiState.collectAsStateWithLifecycle()
    val invoicesState by invoicesViewModel.uiState.collectAsStateWithLifecycle()
    val reportsState by reportsViewModel.uiState.collectAsStateWithLifecycle()
    val technicalReportsState by technicalReportsViewModel.uiState.collectAsStateWithLifecycle()
    val settingsState by settingsViewModel.uiState.collectAsStateWithLifecycle()
    val verificationState by verificationViewModel.uiState.collectAsStateWithLifecycle()
    val dashboardState by dashboardViewModel.uiState.collectAsStateWithLifecycle()

    // Keep the dashboard in sync with changes made elsewhere (e.g. issuing an
    // invoice) by refreshing whenever the user lands on the Home section.
    LaunchedEffect(section) {
        if (section == WorkspaceSection.Home) {
            dashboardViewModel.refresh()
        }
    }

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = MaterialTheme.colorScheme.background,
        topBar = {
            CenterAlignedTopAppBar(
                title = {
                    Text(
                        text = "FacturaPro",
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                        style = MaterialTheme.typography.titleLarge,
                    )
                },
                actions = {
                    IconButton(onClick = { section = WorkspaceSection.Verify }) {
                        Icon(
                            imageVector = Icons.Outlined.QrCodeScanner,
                            contentDescription = "Verificar documento",
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    IconButton(onClick = onLogout) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Outlined.Logout,
                            contentDescription = "Cerrar sesión",
                            tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background,
                    titleContentColor = MaterialTheme.colorScheme.primary,
                ),
            )
        },
        bottomBar = {
            FacturaProBottomBar(
                current = section,
                onSelect = { section = it },
            )
        },
        floatingActionButton = {
            if (section == WorkspaceSection.Home || section == WorkspaceSection.Invoices || section == WorkspaceSection.TechnicalReports) {
                FloatingActionButton(
                    onClick = {
                        if (section == WorkspaceSection.TechnicalReports) {
                            newTechnicalReportRequest++
                        } else {
                            section = WorkspaceSection.Invoices
                            newInvoiceRequest++
                        }
                    },
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary,
                    shape = RoundedCornerShape(16.dp),
                ) {
                    Icon(
                        imageVector = Icons.Filled.Add,
                        contentDescription = if (section == WorkspaceSection.TechnicalReports) "Nuevo informe" else "Nueva factura",
                        modifier = Modifier.size(24.dp),
                    )
                }
            }
        },
    ) { padding ->
        Box(modifier = Modifier.padding(padding)) {
            when (section) {
                WorkspaceSection.Home -> DashboardScreen(
                    userName = loginState.userName,
                    state = dashboardState,
                    onNewInvoice = {
                        section = WorkspaceSection.Invoices
                        newInvoiceRequest++
                    },
                    onSeeAllInvoices = { section = WorkspaceSection.Invoices },
                    onOpenInvoice = { invoiceId ->
                        section = WorkspaceSection.Invoices
                        openInvoiceId = invoiceId
                        openInvoiceRequest++
                    },
                    onRefresh = dashboardViewModel::refresh,
                )

                WorkspaceSection.Invoices -> InvoicesScreen(
                    state = invoicesState,
                    clients = clientsState.clients,
                    bootstrap = loginState.bootstrap,
                    openCreateRequest = newInvoiceRequest,
                    openInvoiceRequest = openInvoiceRequest,
                    requestedInvoiceId = openInvoiceId,
                    openRequestedInvoiceForEdit = false,
                    onSearchChanged = invoicesViewModel::onSearchChanged,
                    onRefresh = invoicesViewModel::refresh,
                    onDocumentTypeFilterChanged = invoicesViewModel::onDocumentTypeFilterChanged,
                    onFiscalProfileFilterChanged = invoicesViewModel::onFiscalProfileFilterChanged,
                    onSelectInvoice = invoicesViewModel::loadDetail,
                    onClearSelection = invoicesViewModel::clearSelectedInvoice,
                    onCreateInvoice = invoicesViewModel::createInvoice,
                    onUpdateInvoice = invoicesViewModel::updateInvoice,
                    onIssueInvoice = invoicesViewModel::issueSelectedInvoice,
                    onLoadPreview = invoicesViewModel::loadPreviewForSelectedInvoice,
                    onLoadIssuePreview = invoicesViewModel::loadIssuePreviewForSelectedInvoice,
                    onLoadDraftPreview = invoicesViewModel::loadPreviewForDraft,
                    onClearPreview = invoicesViewModel::clearPreview,
                    onGeneratePdf = invoicesViewModel::generatePdfForSelectedInvoice,
                    onDownloadPdf = invoicesViewModel::downloadPdfForSelectedInvoice,
                    onViewPdf = invoicesViewModel::viewPdfForSelectedInvoice,
                    onPrintPdf = invoicesViewModel::printPdfForSelectedInvoice,
                    onSharePdfToWhatsApp = invoicesViewModel::sharePdfToWhatsAppForSelectedInvoice,
                    onIssueAndPreparePdf = invoicesViewModel::issueSelectedInvoiceAndPreparePdf,
                    onConsumeSavedEvent = invoicesViewModel::consumeSavedInvoiceEvent,
                    onConsumeSharedPdfEvent = invoicesViewModel::consumeSharedPdfEvent,
                    onClearInternalPdf = invoicesViewModel::clearInternalPdfViewer,
                    onConvertQuotation = invoicesViewModel::convertSelectedQuotation,
                    onRegisterPayment = invoicesViewModel::registerPaymentForSelectedInvoice,
                )

                WorkspaceSection.Clients -> ClientsScreen(
                    state = clientsState,
                    onSearchChanged = clientsViewModel::onSearchChanged,
                    onRefresh = clientsViewModel::refresh,
                    onCreateClient = clientsViewModel::createClient,
                    onUpdateClient = clientsViewModel::updateClient,
                    onStartEdit = clientsViewModel::startEdit,
                    onEndEdit = clientsViewModel::endEdit,
                    onConsumeSaveEvent = clientsViewModel::consumeSaveEvent,
                )

                WorkspaceSection.Reports -> ReportsScreen(
                    state = reportsState,
                    bootstrap = loginState.bootstrap,
                    onDateFromChanged = reportsViewModel::onDateFromChanged,
                    onDateToChanged = reportsViewModel::onDateToChanged,
                    onRefresh = reportsViewModel::refresh,
                    onClearFilters = reportsViewModel::clearFilters,
                )

                WorkspaceSection.TechnicalReports -> TechnicalReportsScreen(
                    state = technicalReportsState,
                    clients = clientsState.clients,
                    bootstrap = loginState.bootstrap,
                    openCreateRequest = newTechnicalReportRequest,
                    onSearchChanged = technicalReportsViewModel::onSearchChanged,
                    onRefresh = technicalReportsViewModel::refresh,
                    onSelectReport = technicalReportsViewModel::loadDetail,
                    onClearSelection = technicalReportsViewModel::clearSelection,
                    onCreateReport = technicalReportsViewModel::createReport,
                    onUpdateReport = technicalReportsViewModel::updateReport,
                    onDeleteOrCancelReport = technicalReportsViewModel::deleteOrCancelSelectedReport,
                    onLoadPreview = technicalReportsViewModel::loadPreviewForSelectedReport,
                    onClearPreview = technicalReportsViewModel::clearPreview,
                    onGenerateAndViewPdf = technicalReportsViewModel::generateAndViewPdfForSelectedReport,
                    onFiscalProfileChanged = technicalReportsViewModel::loadSettings,
                    onClearInternalPdf = technicalReportsViewModel::clearInternalPdfViewer,
                    onConsumeSavedEvent = technicalReportsViewModel::consumeSavedEvent,
                )

                WorkspaceSection.Calendar -> CalendarScreen(calendarViewModel)

                WorkspaceSection.Verify -> VerificationScreen(
                    state = verificationState,
                    onNumberChanged = verificationViewModel::onNumberChanged,
                    onCodeChanged = verificationViewModel::onCodeChanged,
                    onVerify = verificationViewModel::verify,
                    onScanned = verificationViewModel::onScanned,
                    onScanFailed = verificationViewModel::onScanFailed,
                )

                WorkspaceSection.Settings -> SettingsScreen(
                    state = loginState,
                    settingsState = settingsState,
                    onRetryBootstrap = onRetryBootstrap,
                    onOpenReports = { section = WorkspaceSection.Reports },
                    onLoadPrinters = settingsViewModel::loadBluetoothPrinters,
                    onSavePrinter = settingsViewModel::savePrinter,
                    onClearPrinter = settingsViewModel::clearPrinter,
                    onServerUrlChanged = settingsViewModel::onServerUrlChanged,
                    onSaveServerUrl = settingsViewModel::saveServerUrl,
                    onResetServerUrl = settingsViewModel::resetServerUrl,
                    onBluetoothPermissionsDenied = settingsViewModel::onBluetoothPermissionsDenied,
                    onLogout = onLogout,
                )
            }
        }
    }
}

@Composable
private fun FacturaProBottomBar(
    current: WorkspaceSection,
    onSelect: (WorkspaceSection) -> Unit,
) {
    NavigationBar(
        containerColor = MaterialTheme.colorScheme.surfaceContainerLowest,
        tonalElevation = 0.dp,
    ) {
        bottomNavItems.forEach { item ->
            val selected = current == item.section
            NavigationBarItem(
                selected = selected,
                onClick = { onSelect(item.section) },
                icon = {
                    Icon(
                        imageVector = item.icon,
                        contentDescription = item.label,
                        modifier = Modifier.size(22.dp),
                    )
                },
                label = {
                    Text(
                        text = item.label,
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                    )
                },
                alwaysShowLabel = true,
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = MaterialTheme.colorScheme.primary,
                    selectedTextColor = MaterialTheme.colorScheme.primary,
                    indicatorColor = MaterialTheme.colorScheme.secondaryContainer,
                    unselectedIconColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    unselectedTextColor = MaterialTheme.colorScheme.onSurfaceVariant,
                ),
            )
        }
    }
}

enum class WorkspaceSection {
    Home,
    Invoices,
    Clients,
    Calendar,
    TechnicalReports,
    Reports,
    Verify,
    Settings,
}

private data class BottomItem(
    val section: WorkspaceSection,
    val label: String,
    val icon: ImageVector,
)

private val bottomNavItems = listOf(
    BottomItem(WorkspaceSection.Home, "Inicio", Icons.Outlined.Home),
    BottomItem(WorkspaceSection.Invoices, "Facturas", Icons.Outlined.Description),
    BottomItem(WorkspaceSection.TechnicalReports, "Informes", Icons.Outlined.Assignment),
    BottomItem(WorkspaceSection.Calendar, "Calendario", Icons.Outlined.CalendarMonth),
    BottomItem(WorkspaceSection.Settings, "Ajustes", Icons.Outlined.Settings),
)
