package com.facturador.facturapro.ui.invoices

import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceSummary

data class InvoicesUiState(
    val invoices: List<InvoiceSummary> = emptyList(),
    val searchQuery: String = "",
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isDetailLoading: Boolean = false,
    val isPreviewLoading: Boolean = false,
    val selectedInvoice: InvoiceDetail? = null,
    val previewHtml: String? = null,
    val errorMessage: String? = null,
    val savedInvoiceId: Long? = null,
    val pendingPdfAction: PendingInvoicePdfAction? = null,
    val internalPdfPath: String? = null,
)

data class PendingInvoicePdfAction(
    val absolutePath: String,
    val action: InvoicePdfAction,
)

enum class InvoicePdfAction {
    Share,
    WhatsApp,
    Print,
    View,
}
