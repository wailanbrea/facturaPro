package com.facturador.facturapro.domain.model

enum class VerificationStatus {
    AUTHENTIC,
    ALTERED,
    CODE_MISMATCH,
    NOT_FOUND,
    UNKNOWN,
}

data class VerifiedInvoice(
    val invoiceNumber: String?,
    val documentType: String?,
    val sellerName: String?,
    val sellerTaxId: String?,
    val clientName: String?,
    val clientTaxId: String?,
    val invoiceDate: String?,
    val currencyCode: String?,
    val total: String?,
)

data class InvoiceVerification(
    val status: VerificationStatus,
    val authentic: Boolean,
    val invoice: VerifiedInvoice?,
)
