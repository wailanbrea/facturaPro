package com.facturador.facturapro.data.repository

import com.facturador.facturapro.domain.model.AuthSession
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.DashboardSummary
import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceSummary
import com.facturador.facturapro.domain.model.InvoiceVerification
import com.facturador.facturapro.domain.model.OperationalReport
import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.domain.model.ReportFilters
import com.facturador.facturapro.domain.model.SavedPrinter
import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportDraft
import com.facturador.facturapro.domain.model.TechnicalReportSetting
import com.facturador.facturapro.domain.model.TechnicalReportSummary
import java.io.File
import kotlinx.coroutines.flow.Flow

interface SessionStoreContract {
    val session: Flow<AuthSession?>

    suspend fun save(session: AuthSession)

    suspend fun clear()
}

interface AuthRepositoryContract {
    val session: Flow<AuthSession?>

    suspend fun login(email: String, password: String): Result<AuthSession>

    suspend fun logout()
}

interface SettingsRepositoryContract {
    suspend fun loadBootstrap(): Result<BootstrapCatalogs>
}

interface DashboardRepositoryContract {
    suspend fun load(): Result<DashboardSummary>
}

interface InvoiceRepositoryContract {
    suspend fun list(search: String? = null): Result<List<InvoiceSummary>>

    suspend fun detail(invoiceId: Long): Result<InvoiceDetail>

    suspend fun preview(invoiceId: Long): Result<String>

    suspend fun previewIssue(invoiceId: Long): Result<String>

    suspend fun previewDraft(draft: InvoiceDraft): Result<String>

    suspend fun create(draft: InvoiceDraft): Result<InvoiceDetail>

    suspend fun update(invoiceId: Long, draft: InvoiceDraft): Result<InvoiceDetail>

    suspend fun issue(invoiceId: Long): Result<InvoiceDetail>

    suspend fun generatePdf(invoiceId: Long): Result<String>

    suspend fun downloadPdf(invoiceId: Long, fileName: String): Result<File>

    suspend fun verify(number: String, code: String): Result<InvoiceVerification>

    suspend fun convert(invoiceId: Long): Result<InvoiceDetail>

    suspend fun markPaid(invoiceId: Long, amount: Double, paymentMethod: String, reference: String?, date: String): Result<InvoiceDetail>
}

interface ReportRepositoryContract {
    suspend fun load(filters: ReportFilters): Result<OperationalReport>
}

interface TechnicalReportRepositoryContract {
    suspend fun settings(): Result<TechnicalReportSetting>

    suspend fun list(search: String? = null): Result<List<TechnicalReportSummary>>

    suspend fun detail(reportId: Long): Result<TechnicalReportDetail>

    suspend fun preview(reportId: Long): Result<String>

    suspend fun create(draft: TechnicalReportDraft): Result<TechnicalReportDetail>

    suspend fun update(reportId: Long, draft: TechnicalReportDraft): Result<TechnicalReportDetail>

    suspend fun deleteOrCancel(reportId: Long): Result<Unit>

    suspend fun generatePdf(reportId: Long): Result<String>

    suspend fun downloadPdf(reportId: Long, fileName: String): Result<File>
}

interface PrinterRepositoryContract {
    val selectedPrinter: Flow<SavedPrinter?>

    suspend fun pairedBluetoothPrinters(): Result<List<PrinterDevice>>

    suspend fun savePrinter(printer: PrinterDevice): Result<Unit>

    suspend fun clearPrinter(): Result<Unit>
}
