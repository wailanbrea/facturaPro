package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.InvoiceDetail
import com.facturador.facturapro.domain.model.InvoiceDraft
import com.facturador.facturapro.domain.model.InvoiceDraftItem
import com.facturador.facturapro.domain.model.InvoiceLine
import com.facturador.facturapro.domain.model.InvoiceSummary
import com.google.gson.annotations.SerializedName

data class InvoiceDto(
    val id: Long,
    @SerializedName("invoice_number")
    val invoiceNumber: String? = null,
    @SerializedName("document_type")
    val documentType: String = "invoice",
    @SerializedName("invoice_date")
    val invoiceDate: String,
    @SerializedName("due_date")
    val dueDate: String? = null,
    @SerializedName("payment_term_id")
    val paymentTermId: Long,
    @SerializedName("client_id")
    val clientId: Long? = null,
    @SerializedName("client_name")
    val clientName: String,
    @SerializedName("client_tax_id")
    val clientTaxId: String? = null,
    @SerializedName("client_address")
    val clientAddress: String? = null,
    @SerializedName("currency_id")
    val currencyId: Long,
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    @SerializedName("fiscal_profile_id")
    val fiscalProfileId: Long? = null,
    @SerializedName("logo_path")
    val logoPath: String? = null,
    @SerializedName("bank_account_id")
    val bankAccountId: Long? = null,
    @SerializedName("warranty_id")
    val warrantyId: Long? = null,
    @SerializedName("warranty_text")
    val warrantyText: String? = null,
    @SerializedName("legal_text")
    val legalText: String? = null,
    @SerializedName("conformity_text")
    val conformityText: String? = null,
    val observations: String? = null,
    @SerializedName("amount_received")
    val amountReceived: String,
    val subtotal: String,
    @SerializedName("tax_total")
    val taxTotal: String,
    val total: String,
    @SerializedName("balance_due")
    val balanceDue: String,
    val status: String,
    @SerializedName("prepared_by")
    val preparedBy: String? = null,
    @SerializedName("received_by")
    val receivedBy: String? = null,
    @SerializedName("pdf_path")
    val pdfPath: String? = null,
    val items: List<InvoiceItemDto> = emptyList(),
)

data class InvoiceItemDto(
    val id: Long,
    val description: String,
    val quantity: String,
    @SerializedName("unit_cost")
    val unitCost: String,
    @SerializedName("tax_id")
    val taxId: Long,
    @SerializedName("tax_name")
    val taxName: String? = null,
    @SerializedName("tax_rate")
    val taxRate: String,
    @SerializedName("tax_amount")
    val taxAmount: String,
    @SerializedName("line_subtotal")
    val lineSubtotal: String,
    @SerializedName("line_total")
    val lineTotal: String,
)

data class InvoiceUpsertDto(
    @SerializedName("document_type")
    val documentType: String,
    @SerializedName("invoice_date")
    val invoiceDate: String,
    @SerializedName("payment_term_id")
    val paymentTermId: Long,
    @SerializedName("client_id")
    val clientId: Long? = null,
    @SerializedName("client_name")
    val clientName: String? = null,
    @SerializedName("client_tax_id")
    val clientTaxId: String? = null,
    @SerializedName("client_address")
    val clientAddress: String? = null,
    @SerializedName("client_city")
    val clientCity: String? = null,
    @SerializedName("client_phone")
    val clientPhone: String? = null,
    @SerializedName("client_email")
    val clientEmail: String? = null,
    @SerializedName("currency_id")
    val currencyId: Long,
    @SerializedName("fiscal_profile_id")
    val fiscalProfileId: Long? = null,
    @SerializedName("logo_path")
    val logoPath: String? = null,
    @SerializedName("bank_account_id")
    val bankAccountId: Long? = null,
    @SerializedName("warranty_id")
    val warrantyId: Long? = null,
    @SerializedName("warranty_text")
    val warrantyText: String? = null,
    @SerializedName("legal_text")
    val legalText: String? = null,
    @SerializedName("conformity_text")
    val conformityText: String? = null,
    val observations: String? = null,
    @SerializedName("amount_received")
    val amountReceived: String? = null,
    @SerializedName("prepared_by")
    val preparedBy: String? = null,
    @SerializedName("received_by")
    val receivedBy: String? = null,
    val items: List<InvoiceItemUpsertDto>,
)

data class InvoiceItemUpsertDto(
    val description: String,
    val quantity: String,
    @SerializedName("unit_cost")
    val unitCost: String,
    @SerializedName("tax_id")
    val taxId: Long,
)

fun InvoiceDto.toSummary(): InvoiceSummary = InvoiceSummary(
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

fun InvoiceDto.toDetail(): InvoiceDetail = InvoiceDetail(
    id = id,
    invoiceNumber = invoiceNumber,
    documentType = documentType,
    invoiceDate = invoiceDate,
    dueDate = dueDate,
    clientId = clientId,
    clientName = clientName,
    clientTaxId = clientTaxId,
    clientAddress = clientAddress,
    currencyId = currencyId,
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    paymentTermId = paymentTermId,
    fiscalProfileId = fiscalProfileId,
    logoPath = logoPath,
    bankAccountId = bankAccountId,
    warrantyId = warrantyId,
    warrantyText = warrantyText,
    legalText = legalText,
    conformityText = conformityText,
    observations = observations,
    subtotal = subtotal,
    taxTotal = taxTotal,
    total = total,
    amountReceived = amountReceived,
    balanceDue = balanceDue,
    status = status,
    preparedBy = preparedBy,
    receivedBy = receivedBy,
    pdfPath = pdfPath,
    items = items.map { item ->
        InvoiceLine(
            id = item.id,
            description = item.description,
            quantity = item.quantity,
            unitCost = item.unitCost,
            taxId = item.taxId,
            taxName = item.taxName,
            taxRate = item.taxRate,
            taxAmount = item.taxAmount,
            lineSubtotal = item.lineSubtotal,
            lineTotal = item.lineTotal,
        )
    },
)

fun InvoiceDraft.toRemote(): InvoiceUpsertDto = InvoiceUpsertDto(
    documentType = documentType,
    invoiceDate = invoiceDate,
    paymentTermId = paymentTermId,
    clientId = clientId,
    clientName = clientName?.trim().takeUnless { it.isNullOrEmpty() },
    clientTaxId = clientTaxId?.trim().takeUnless { it.isNullOrEmpty() },
    clientAddress = clientAddress?.trim().takeUnless { it.isNullOrEmpty() },
    clientCity = clientCity?.trim().takeUnless { it.isNullOrEmpty() },
    clientPhone = clientPhone?.trim().takeUnless { it.isNullOrEmpty() },
    clientEmail = clientEmail?.trim().takeUnless { it.isNullOrEmpty() },
    currencyId = currencyId,
    fiscalProfileId = fiscalProfileId,
    logoPath = logoPath?.trim().takeUnless { it.isNullOrEmpty() },
    bankAccountId = bankAccountId,
    warrantyId = warrantyId,
    warrantyText = warrantyText?.trim().takeUnless { it.isNullOrEmpty() },
    legalText = legalText?.trim().takeUnless { it.isNullOrEmpty() },
    conformityText = conformityText?.trim().takeUnless { it.isNullOrEmpty() },
    observations = observations?.trim().takeUnless { it.isNullOrEmpty() },
    amountReceived = amountReceived?.trim().takeUnless { it.isNullOrEmpty() },
    preparedBy = preparedBy?.trim().takeUnless { it.isNullOrEmpty() },
    receivedBy = receivedBy?.trim().takeUnless { it.isNullOrEmpty() },
    items = items.map(InvoiceDraftItem::toRemote),
)

fun InvoiceDraftItem.toRemote(): InvoiceItemUpsertDto = InvoiceItemUpsertDto(
    description = description.trim(),
    quantity = quantity.trim(),
    unitCost = unitCost.trim(),
    taxId = taxId,
)
