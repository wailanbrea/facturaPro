package com.facturador.facturapro.ui.invoices

import com.facturador.facturapro.data.repository.InvoiceRepositoryContract
import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceDraftItem
import com.facturador.facturapro.domain.model.InvoiceLine
import com.facturador.facturapro.domain.model.InvoiceSummary
import com.facturador.facturapro.testutil.MainDispatcherRule
import java.io.File
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class InvoicesViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun create_invoice_updates_selected_invoice_and_list_state() = runTest {
        val existing = sampleInvoiceSummary(
            id = 1,
            invoiceNumber = "FAC-000001",
            invoiceDate = "2026-05-20",
            clientName = "Cliente Inicial",
            total = "100.0000",
            balanceDue = "100.0000",
            status = "issued",
        )
        val created = sampleInvoiceDetail(
            id = 2,
            invoiceNumber = null,
            invoiceDate = "2026-05-21",
            clientName = "Cliente Nuevo",
            total = "236.0000",
            balanceDue = "236.0000",
            status = "draft",
        )
        val repository = FakeInvoiceRepository(
            listResult = Result.success(listOf(existing)),
            createResult = Result.success(created),
        )
        val viewModel = InvoicesViewModel(repository)

        advanceUntilIdle()
        viewModel.createInvoice(sampleDraft())
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(1, repository.createCalls)
        assertEquals("Cliente Nuevo", state.selectedInvoice?.clientName)
        assertEquals(2L, state.savedInvoiceId)
        assertEquals(listOf(2L, 1L), state.invoices.map { it.id })
        assertEquals("2026-05-21", repository.lastCreatedDraft?.invoiceDate)
        assertNull(state.errorMessage)
        assertTrue(!state.isSaving)
    }

    @Test
    fun preview_draft_loads_html_without_creating_invoice() = runTest {
        val repository = FakeInvoiceRepository(
            listResult = Result.success(emptyList()),
            previewDraftResult = Result.success("<html>Vista previa borrador</html>"),
        )
        val viewModel = InvoicesViewModel(repository)

        advanceUntilIdle()
        viewModel.loadPreviewForDraft(sampleDraft())
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(1, repository.previewDraftCalls)
        assertEquals(0, repository.createCalls)
        assertEquals("<html>Vista previa borrador</html>", state.previewHtml)
        assertNull(state.errorMessage)
        assertTrue(!state.isPreviewLoading)
    }

    @Test
    fun issue_preview_loads_html_for_selected_draft_invoice() = runTest {
        val draftInvoice = sampleInvoiceDetail(
            id = 2,
            invoiceNumber = null,
            invoiceDate = "2026-05-21",
            clientName = "Cliente Nuevo",
            total = "242.0000",
            balanceDue = "242.0000",
            status = "draft",
        )
        val repository = FakeInvoiceRepository(
            listResult = Result.success(emptyList()),
            detailResult = Result.success(draftInvoice),
            previewIssueResult = Result.success("<html>Factura provisional</html>"),
        )
        val viewModel = InvoicesViewModel(repository)

        advanceUntilIdle()
        viewModel.loadDetail(draftInvoice.id)
        advanceUntilIdle()
        viewModel.loadIssuePreviewForSelectedInvoice()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(1, repository.previewIssueCalls)
        assertEquals("<html>Factura provisional</html>", state.previewHtml)
        assertNull(state.errorMessage)
        assertTrue(!state.isPreviewLoading)
    }

    @Test
    fun issue_and_prepare_pdf_issues_generates_downloads_and_emits_print_event() = runTest {
        val draftInvoice = sampleInvoiceDetail(
            id = 2,
            invoiceNumber = null,
            invoiceDate = "2026-05-21",
            clientName = "Cliente Nuevo",
            total = "242.0000",
            balanceDue = "242.0000",
            status = "draft",
        )
        val issuedInvoice = draftInvoice.copy(
            invoiceNumber = "FAC-000001",
            status = "issued",
        )
        val pdfFile = File("build/test-pdfs/FAC-000001.pdf")
        val repository = FakeInvoiceRepository(
            listResult = Result.success(emptyList()),
            detailResult = Result.success(draftInvoice),
            issueResult = Result.success(issuedInvoice),
            generatePdfResult = Result.success("invoices/FAC-000001.pdf"),
            downloadPdfResult = Result.success(pdfFile),
        )
        val viewModel = InvoicesViewModel(repository)

        advanceUntilIdle()
        viewModel.loadDetail(draftInvoice.id)
        advanceUntilIdle()
        viewModel.issueSelectedInvoiceAndPreparePdf(InvoicePdfAction.Print)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(1, repository.issueCalls)
        assertEquals(1, repository.generatePdfCalls)
        assertEquals(1, repository.downloadPdfCalls)
        assertEquals("FAC-000001", state.selectedInvoice?.invoiceNumber)
        assertEquals("invoices/FAC-000001.pdf", state.selectedInvoice?.pdfPath)
        assertEquals(pdfFile.absolutePath, state.pendingPdfAction?.absolutePath)
        assertEquals(InvoicePdfAction.Print, state.pendingPdfAction?.action)
        assertNull(state.errorMessage)
        assertTrue(!state.isSaving)
    }
}

private class FakeInvoiceRepository(
    private val listResult: Result<List<InvoiceSummary>> = Result.success(emptyList()),
    private val detailResult: Result<InvoiceDetail> = Result.failure(IllegalStateException("Not configured")),
    private val createResult: Result<InvoiceDetail> = Result.failure(IllegalStateException("Not configured")),
    private val issueResult: Result<InvoiceDetail> = Result.failure(IllegalStateException("Not configured")),
    private val generatePdfResult: Result<String> = Result.failure(IllegalStateException("Not configured")),
    private val downloadPdfResult: Result<File> = Result.failure(IllegalStateException("Not configured")),
    private val previewIssueResult: Result<String> = Result.failure(IllegalStateException("Not configured")),
    private val previewDraftResult: Result<String> = Result.failure(IllegalStateException("Not configured")),
) : InvoiceRepositoryContract {
    var createCalls: Int = 0
        private set

    var previewDraftCalls: Int = 0
        private set

    var previewIssueCalls: Int = 0
        private set

    var issueCalls: Int = 0
        private set

    var generatePdfCalls: Int = 0
        private set

    var downloadPdfCalls: Int = 0
        private set

    var lastCreatedDraft: InvoiceDraft? = null
        private set

    override suspend fun list(
        search: String?,
        documentType: String?,
        fiscalProfileId: Long?,
    ): Result<List<InvoiceSummary>> = listResult

    override suspend fun detail(invoiceId: Long): Result<InvoiceDetail> = detailResult

    override suspend fun preview(invoiceId: Long): Result<String> = Result.failure(UnsupportedOperationException())

    override suspend fun previewIssue(invoiceId: Long): Result<String> {
        previewIssueCalls++
        return previewIssueResult
    }

    override suspend fun previewDraft(draft: InvoiceDraft): Result<String> {
        previewDraftCalls++
        return previewDraftResult
    }

    override suspend fun create(draft: InvoiceDraft): Result<InvoiceDetail> {
        createCalls++
        lastCreatedDraft = draft
        return createResult
    }

    override suspend fun update(invoiceId: Long, draft: InvoiceDraft): Result<InvoiceDetail> = Result.failure(UnsupportedOperationException())

    override suspend fun issue(invoiceId: Long): Result<InvoiceDetail> {
        issueCalls++
        return issueResult
    }

    override suspend fun generatePdf(invoiceId: Long): Result<String> {
        generatePdfCalls++
        return generatePdfResult
    }

    override suspend fun downloadPdf(invoiceId: Long, fileName: String): Result<File> {
        downloadPdfCalls++
        return downloadPdfResult
    }

    override suspend fun verify(
        number: String,
        code: String,
    ): Result<com.facturador.facturapro.domain.model.InvoiceVerification> =
        Result.failure(UnsupportedOperationException())

    override suspend fun convert(invoiceId: Long): Result<InvoiceDetail> =
        Result.failure(UnsupportedOperationException())

    override suspend fun markPaid(
        invoiceId: Long,
        amount: Double,
        paymentMethod: String,
        reference: String?,
        date: String
    ): Result<InvoiceDetail> =
        Result.failure(UnsupportedOperationException())
}

private fun sampleDraft(): InvoiceDraft = InvoiceDraft(
    documentType = "invoice",
    invoiceDate = "2026-05-21",
    paymentTermId = 1L,
    clientId = 2L,
    clientName = null,
    clientTaxId = null,
    clientAddress = null,
    clientCity = null,
    clientPhone = null,
    clientEmail = null,
    currencyId = 1L,
    fiscalProfileId = 1L,
    logoPath = null,
    bankAccountId = 1L,
    warrantyId = 1L,
    warrantyText = "Garantia",
    legalText = "Texto legal",
    conformityText = "CONFORMIDAD DEL CLIENTE",
    observations = "Observacion",
    amountReceived = "0",
    preparedBy = "Admin",
    receivedBy = "Cliente",
    items = listOf(
        InvoiceDraftItem(
            description = "Servicio tecnico",
            quantity = "2",
            unitCost = "100",
            taxId = 1L,
        ),
    ),
)

private fun sampleInvoiceSummary(
    id: Long,
    invoiceNumber: String?,
    invoiceDate: String,
    clientName: String,
    total: String,
    balanceDue: String,
    status: String,
): InvoiceSummary = InvoiceSummary(
    id = id,
    invoiceNumber = invoiceNumber,
    documentType = "invoice",
    invoiceDate = invoiceDate,
    dueDate = invoiceDate,
    clientName = clientName,
    currencySymbol = "RD$",
    total = total,
    balanceDue = balanceDue,
    status = status,
    pdfPath = null,
)

private fun sampleInvoiceDetail(
    id: Long,
    invoiceNumber: String?,
    invoiceDate: String,
    clientName: String,
    total: String,
    balanceDue: String,
    status: String,
): InvoiceDetail = InvoiceDetail(
    id = id,
    invoiceNumber = invoiceNumber,
    documentType = "invoice",
    invoiceDate = invoiceDate,
    dueDate = invoiceDate,
    clientId = id,
    clientName = clientName,
    clientTaxId = "001-0000000-1",
    clientAddress = "Santo Domingo",
    currencyId = 1L,
    currencyCode = "DOP",
    currencySymbol = "RD$",
    paymentTermId = 1L,
    fiscalProfileId = 1L,
    logoPath = null,
    bankAccountId = 1L,
    warrantyId = 1L,
    warrantyText = "Garantia",
    legalText = "Texto legal",
    conformityText = "CONFORMIDAD DEL CLIENTE",
    observations = "Observacion",
    subtotal = "200.0000",
    taxTotal = "36.0000",
    total = total,
    amountReceived = "0.0000",
    balanceDue = balanceDue,
    status = status,
    preparedBy = "Admin",
    receivedBy = "Cliente",
    pdfPath = null,
    items = listOf(
        InvoiceLine(
            id = 1L,
            description = "Servicio tecnico",
            quantity = "2.0000",
            unitCost = "100.0000",
            taxId = 1L,
            taxName = "ITBIS 18%",
            taxRate = "18.0000",
            taxAmount = "36.0000",
            lineSubtotal = "200.0000",
            lineTotal = "236.0000",
        ),
    ),
)
