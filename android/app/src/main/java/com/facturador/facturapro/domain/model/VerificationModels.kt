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

data class VerifiedReport(
    val reportNumber: String?,
    val sellerName: String?,
    val recipientName: String?,
    val recipientTaxId: String?,
    val reportDate: String?,
)

data class InvoiceVerification(
    val status: VerificationStatus,
    val authentic: Boolean,
    val type: String?,
    val invoice: VerifiedInvoice?,
    val report: VerifiedReport?,
)
