package com.facturador.facturapro.ui.invoices

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.InvoiceRepositoryContract
import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceSummary
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class InvoicesViewModel(
    private val repository: InvoiceRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(InvoicesUiState())
    val uiState: StateFlow<InvoicesUiState> = _uiState.asStateFlow()

    init {
        refresh()
    }

    fun onSearchChanged(value: String) {
        _uiState.update { it.copy(searchQuery = value, errorMessage = null) }
    }

    fun refresh() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            repository.list(_uiState.value.searchQuery).fold(
                onSuccess = { invoices ->
                    _uiState.update {
                        it.copy(
                            invoices = invoices,
                            isLoading = false,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar facturas.",
                        )
                    }
                },
            )
        }
    }

    fun loadDetail(invoiceId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isDetailLoading = true, errorMessage = null) }
            repository.detail(invoiceId).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            selectedInvoice = invoice,
                            isDetailLoading = false,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isDetailLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la factura.",
                        )
                    }
                },
            )
        }
    }

    fun clearSelectedInvoice() {
        _uiState.update {
            it.copy(
                selectedInvoice = null,
                previewHtml = null,
                errorMessage = null,
                internalPdfPath = null,
                pendingPdfAction = null,
            )
        }
    }

    fun createInvoice(draft: InvoiceDraft) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                    savedInvoiceId = null,
                    internalPdfPath = null,
                    pendingPdfAction = null,
                )
            }

            repository.create(draft).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            savedInvoiceId = invoice.id,
                            selectedInvoice = invoice,
                            invoices = (it.invoices.filterNot { summary -> summary.id == invoice.id } + invoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo crear la factura.",
                        )
                    }
                },
            )
        }
    }

    fun updateInvoice(invoiceId: Long, draft: InvoiceDraft) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                    internalPdfPath = null,
                    pendingPdfAction = null,
                )
            }

            repository.update(invoiceId, draft).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            selectedInvoice = invoice,
                            invoices = (it.invoices.filterNot { summary -> summary.id == invoice.id } + invoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo actualizar la factura.",
                        )
                    }
                },
            )
        }
    }

    fun issueSelectedInvoice() {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                    internalPdfPath = null,
                    pendingPdfAction = null,
                )
            }

            repository.issue(invoiceId).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            selectedInvoice = invoice,
                            invoices = (it.invoices.filterNot { summary -> summary.id == invoice.id } + invoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo emitir la factura.",
                        )
                    }
                },
            )
        }
    }

    fun convertSelectedQuotation() {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                )
            }

            repository.convert(invoiceId).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            selectedInvoice = invoice,
                            invoices = (it.invoices.filterNot { summary -> summary.id == invoice.id } + invoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },
                        )
                    }
                    refresh()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo convertir el presupuesto.",
                        )
                    }
                }
            )
        }
    }

    fun registerPaymentForSelectedInvoice(
        amount: Double,
        paymentMethod: String,
        reference: String?,
        date: String
    ) {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                )
            }

            repository.markPaid(invoiceId, amount, paymentMethod, reference, date).fold(
                onSuccess = { invoice ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            selectedInvoice = invoice,
                            invoices = (it.invoices.filterNot { summary -> summary.id == invoice.id } + invoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo registrar el pago.",
                        )
                    }
                }
            )
        }
    }

    fun issueSelectedInvoiceAndPreparePdf(action: InvoicePdfAction) {
        preparePdfActionForSelectedInvoice(action = action, issueBeforePreparing = true)
    }

    fun loadPreviewForSelectedInvoice() {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isPreviewLoading = true,
                    previewHtml = null,
                    errorMessage = null,
                    internalPdfPath = null,
                )
            }

            repository.preview(invoiceId).fold(
                onSuccess = { html ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            previewHtml = html,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la vista previa.",
                        )
                    }
                },
            )
        }
    }

    fun loadIssuePreviewForSelectedInvoice() {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isPreviewLoading = true,
                    previewHtml = null,
                    errorMessage = null,
                    internalPdfPath = null,
                )
            }

            repository.previewIssue(invoiceId).fold(
                onSuccess = { html ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            previewHtml = html,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la vista previa de emision.",
                        )
                    }
                },
            )
        }
    }

    fun loadPreviewForDraft(draft: InvoiceDraft) {
        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isPreviewLoading = true,
                    previewHtml = null,
                    errorMessage = null,
                    internalPdfPath = null,
                )
            }

            repository.previewDraft(draft).fold(
                onSuccess = { html ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            previewHtml = html,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la vista previa del borrador.",
                        )
                    }
                },
            )
        }
    }

    fun clearPreview() {
        _uiState.update { it.copy(previewHtml = null, isPreviewLoading = false) }
    }

    fun generatePdfForSelectedInvoice() {
        val invoiceId = _uiState.value.selectedInvoice?.id ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                    internalPdfPath = null,
                    pendingPdfAction = null,
                )
            }

            repository.generatePdf(invoiceId).fold(
                onSuccess = {
                    _uiState.update { it.copy(isSaving = false) }
                    loadDetail(invoiceId)
                    refresh()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo generar el PDF.",
                        )
                    }
                },
            )
        }
    }

    fun downloadPdfForSelectedInvoice() {
        preparePdfActionForSelectedInvoice(action = InvoicePdfAction.Share, issueBeforePreparing = false)
    }

    fun printPdfForSelectedInvoice() {
        preparePdfActionForSelectedInvoice(action = InvoicePdfAction.Print, issueBeforePreparing = false)
    }

    fun sharePdfToWhatsAppForSelectedInvoice() {
        preparePdfActionForSelectedInvoice(action = InvoicePdfAction.WhatsApp, issueBeforePreparing = false)
    }

    fun viewPdfForSelectedInvoice() {
        val invoice = _uiState.value.selectedInvoice ?: return
        val shouldIssue = invoice.status == "draft"
        preparePdfActionForSelectedInvoice(action = InvoicePdfAction.View, issueBeforePreparing = shouldIssue)
    }

    private fun preparePdfActionForSelectedInvoice(
        action: InvoicePdfAction,
        issueBeforePreparing: Boolean,
    ) {
        val invoice = _uiState.value.selectedInvoice ?: return

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSaving = true,
                    errorMessage = null,
                    pendingPdfAction = null,
                    internalPdfPath = null,
                )
            }

            runCatching {
                var readyInvoice = invoice

                if (issueBeforePreparing && readyInvoice.status == "draft") {
                    readyInvoice = repository.issue(readyInvoice.id).getOrThrow()
                }

                if (readyInvoice.invoiceNumber == null) {
                    error("La factura debe estar emitida antes de generar el PDF.")
                }

                val generatedPdfPath = repository.generatePdf(readyInvoice.id).getOrThrow()
                readyInvoice = readyInvoice.copy(pdfPath = generatedPdfPath)

                val fileName = "${readyInvoice.invoiceNumber ?: "factura_${readyInvoice.id}"}.pdf"
                val file = repository.downloadPdf(readyInvoice.id, fileName).getOrThrow()

                readyInvoice to file
            }.fold(
                onSuccess = { (readyInvoice, file) ->
                    _uiState.update { current ->
                        current.copy(
                            isSaving = false,
                            selectedInvoice = readyInvoice,
                            invoices = (current.invoices.filterNot { summary -> summary.id == readyInvoice.id } + readyInvoice.toSummary())
                                .sortedByDescending { summary -> summary.invoiceDate },

                            // IMPORTANTE:
                            // View abre el visor interno. No debe mandar pendingPdfAction.
                            internalPdfPath = if (action == InvoicePdfAction.View) {
                                file.absolutePath
                            } else {
                                null
                            },

                            // Share / WhatsApp / Print siguen usando efectos externos.
                            pendingPdfAction = if (action == InvoicePdfAction.View) {
                                null
                            } else {
                                PendingInvoicePdfAction(
                                    absolutePath = file.absolutePath,
                                    action = action,
                                )
                            },
                        )
                    }

                    refresh()
                },
                onFailure = { error ->
                    Log.e(
                        "FacturaProPDF",
                        "preparePdfActionForSelectedInvoice failed action=$action: ${error::class.java.name}: ${error.message}",
                        error,
                    )
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: error.toString(),
                            internalPdfPath = null,
                            pendingPdfAction = null,
                        )
                    }
                },
            )
        }
    }

    fun consumeSavedInvoiceEvent() {
        _uiState.update { it.copy(savedInvoiceId = null) }
    }

    fun consumeSharedPdfEvent() {
        _uiState.update { it.copy(pendingPdfAction = null) }
    }

    fun clearInternalPdfViewer() {
        _uiState.update { it.copy(internalPdfPath = null) }
    }

    companion object {
        fun factory(repository: InvoiceRepositoryContract): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(InvoicesViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return InvoicesViewModel(repository) as T
            }
        }
    }
}

private fun InvoiceDetail.toSummary(): InvoiceSummary = InvoiceSummary(
    id = id,
    invoiceNumber = invoiceNumber,
    documentType = documentType,
    invoiceDate = invoiceDate,
    dueDate = dueDate,
    clientName = clientName,
    currencySymbol = currencySymbol,
    total = total,
    balanceDue = balanceDue,
    status = status,
    pdfPath = pdfPath,
)
