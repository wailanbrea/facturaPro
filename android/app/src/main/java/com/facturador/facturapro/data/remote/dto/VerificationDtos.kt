package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.InvoiceVerification
import com.facturador.facturapro.domain.model.VerificationStatus
import com.facturador.facturapro.domain.model.VerifiedInvoice
import com.facturador.facturapro.domain.model.VerifiedReport
import com.google.gson.annotations.SerializedName

data class InvoiceVerificationResponseDto(
    val status: String? = null,
    val type: String? = null,
    val authentic: Boolean = false,
    val invoice: VerifiedInvoiceDto? = null,
    val report: VerifiedReportDto? = null,
)

data class VerifiedInvoiceDto(
    @SerializedName("invoice_number") val invoiceNumber: String? = null,
    @SerializedName("document_type") val documentType: String? = null,
    @SerializedName("seller_name") val sellerName: String? = null,
    @SerializedName("seller_tax_id") val sellerTaxId: String? = null,
    @SerializedName("client_name") val clientName: String? = null,
    @SerializedName("client_tax_id") val clientTaxId: String? = null,
    @SerializedName("invoice_date") val invoiceDate: String? = null,
    @SerializedName("currency_code") val currencyCode: String? = null,
    val total: String? = null,
)

data class VerifiedReportDto(
    @SerializedName("report_number") val reportNumber: String? = null,
    @SerializedName("seller_name") val sellerName: String? = null,
    @SerializedName("recipient_name") val recipientName: String? = null,
    @SerializedName("recipient_tax_id") val recipientTaxId: String? = null,
    @SerializedName("report_date") val reportDate: String? = null,
)

fun InvoiceVerificationResponseDto.toDomain(): InvoiceVerification = InvoiceVerification(
    status = when (status) {
        "authentic" -> VerificationStatus.AUTHENTIC
        "altered" -> VerificationStatus.ALTERED
        "code_mismatch" -> VerificationStatus.CODE_MISMATCH
        "not_found" -> VerificationStatus.NOT_FOUND
        else -> VerificationStatus.UNKNOWN
    },
    authentic = authentic,
    type = type,
    invoice = invoice?.let {
        VerifiedInvoice(
            invoiceNumber = it.invoiceNumber,
            documentType = it.documentType,
            sellerName = it.sellerName,
            sellerTaxId = it.sellerTaxId,
            clientName = it.clientName,
            clientTaxId = it.clientTaxId,
            invoiceDate = it.invoiceDate,
            currencyCode = it.currencyCode,
            total = it.total,
        )
    },
    report = report?.let {
        VerifiedReport(
            reportNumber = it.reportNumber,
            sellerName = it.sellerName,
            recipientName = it.recipientName,
            recipientTaxId = it.recipientTaxId,
            reportDate = it.reportDate,
        )
    },
)
