package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportDraft
import com.facturador.facturapro.domain.model.TechnicalReportSetting
import com.facturador.facturapro.domain.model.TechnicalReportSummary
import com.google.gson.annotations.SerializedName

data class TechnicalReportDto(
    val id: Long,
    @SerializedName("report_number")
    val reportNumber: String,
    @SerializedName("report_date")
    val reportDate: String,
    @SerializedName("fiscal_profile_id")
    val fiscalProfileId: Long,
    @SerializedName("seller_name")
    val sellerName: String,
    @SerializedName("seller_tax_id")
    val sellerTaxId: String? = null,
    @SerializedName("seller_address")
    val sellerAddress: String? = null,
    @SerializedName("seller_city")
    val sellerCity: String? = null,
    @SerializedName("seller_logo_path")
    val logoPath: String? = null,
    @SerializedName("client_id")
    val clientId: Long? = null,
    @SerializedName("recipient_name")
    val recipientName: String,
    @SerializedName("recipient_tax_id")
    val recipientTaxId: String? = null,
    @SerializedName("recipient_address")
    val recipientAddress: String,
    @SerializedName("section_1_title")
    val section1Title: String,
    @SerializedName("section_1_content")
    val section1Content: String? = null,
    @SerializedName("section_2_title")
    val section2Title: String,
    @SerializedName("section_2_content")
    val section2Content: String? = null,
    @SerializedName("section_3_title")
    val section3Title: String,
    @SerializedName("section_3_content")
    val section3Content: String? = null,
    @SerializedName("section_4_title")
    val section4Title: String,
    @SerializedName("section_4_content")
    val section4Content: String? = null,
    @SerializedName("intro_text")
    val introText: String? = null,
    @SerializedName("final_text")
    val finalText: String? = null,
    val notes: String? = null,
    val status: String,
    @SerializedName("status_label")
    val statusLabel: String = "",
    @SerializedName("pdf_path")
    val pdfPath: String? = null,
    @SerializedName("verification_code")
    val verificationCode: String? = null,
)

data class TechnicalReportUpsertDto(
    @SerializedName("report_number")
    val reportNumber: String? = null,
    @SerializedName("report_date")
    val reportDate: String,
    @SerializedName("fiscal_profile_id")
    val fiscalProfileId: Long,
    @SerializedName("logo_path")
    val logoPath: String? = null,
    @SerializedName("client_id")
    val clientId: Long? = null,
    @SerializedName("recipient_name")
    val recipientName: String,
    @SerializedName("recipient_tax_id")
    val recipientTaxId: String? = null,
    @SerializedName("recipient_address")
    val recipientAddress: String,
    @SerializedName("section_1_title")
    val section1Title: String,
    @SerializedName("section_1_content")
    val section1Content: String? = null,
    @SerializedName("section_2_title")
    val section2Title: String,
    @SerializedName("section_2_content")
    val section2Content: String? = null,
    @SerializedName("section_3_title")
    val section3Title: String,
    @SerializedName("section_3_content")
    val section3Content: String? = null,
    @SerializedName("section_4_title")
    val section4Title: String,
    @SerializedName("section_4_content")
    val section4Content: String? = null,
    @SerializedName("intro_text")
    val introText: String? = null,
    @SerializedName("final_text")
    val finalText: String? = null,
    val notes: String? = null,
    val status: String = "draft",
)

data class TechnicalReportSettingDto(
    @SerializedName("section_1_default_title")
    val section1DefaultTitle: String,
    @SerializedName("section_2_default_title")
    val section2DefaultTitle: String,
    @SerializedName("section_3_default_title")
    val section3DefaultTitle: String,
    @SerializedName("section_4_default_title")
    val section4DefaultTitle: String,
    @SerializedName("intro_text")
    val introText: String? = null,
    @SerializedName("final_text")
    val finalText: String? = null,
    @SerializedName("report_prefix")
    val reportPrefix: String,
    @SerializedName("next_report_number")
    val nextReportNumber: Int,
    @SerializedName("number_length")
    val numberLength: Int,
    @SerializedName("allow_manual_number")
    val allowManualNumber: Boolean,
    @SerializedName("next_number_preview")
    val nextNumberPreview: String,
)

fun TechnicalReportDto.toSummary(): TechnicalReportSummary = TechnicalReportSummary(
    id = id,
    reportNumber = reportNumber,
    reportDate = reportDate,
    recipientName = recipientName,
    recipientAddress = recipientAddress,
    sellerName = sellerName,
    status = status,
    statusLabel = statusLabel.ifBlank { status.toReportStatusLabel() },
    pdfPath = pdfPath,
)

fun TechnicalReportDto.toDetail(): TechnicalReportDetail = TechnicalReportDetail(
    id = id,
    reportNumber = reportNumber,
    reportDate = reportDate,
    fiscalProfileId = fiscalProfileId,
    sellerName = sellerName,
    sellerTaxId = sellerTaxId,
    sellerAddress = sellerAddress,
    sellerCity = sellerCity,
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
    introText = introText,
    finalText = finalText,
    notes = notes,
    status = status,
    statusLabel = statusLabel.ifBlank { status.toReportStatusLabel() },
    pdfPath = pdfPath,
    verificationCode = verificationCode,
)

fun TechnicalReportDraft.toRemote(): TechnicalReportUpsertDto = TechnicalReportUpsertDto(
    reportNumber = reportNumber?.trim().takeUnless { it.isNullOrEmpty() },
    reportDate = reportDate,
    fiscalProfileId = fiscalProfileId,
    logoPath = logoPath?.trim().takeUnless { it.isNullOrEmpty() },
    clientId = clientId,
    recipientName = recipientName.trim(),
    recipientTaxId = recipientTaxId?.trim().takeUnless { it.isNullOrEmpty() },
    recipientAddress = recipientAddress.trim(),
    section1Title = section1Title.trim(),
    section1Content = section1Content?.trim().takeUnless { it.isNullOrEmpty() },
    section2Title = section2Title.trim(),
    section2Content = section2Content?.trim().takeUnless { it.isNullOrEmpty() },
    section3Title = section3Title.trim(),
    section3Content = section3Content?.trim().takeUnless { it.isNullOrEmpty() },
    section4Title = section4Title.trim(),
    section4Content = section4Content?.trim().takeUnless { it.isNullOrEmpty() },
    introText = introText?.trim().takeUnless { it.isNullOrEmpty() },
    finalText = finalText?.trim().takeUnless { it.isNullOrEmpty() },
    notes = notes?.trim().takeUnless { it.isNullOrEmpty() },
    status = status,
)

fun TechnicalReportSettingDto.toDomain(): TechnicalReportSetting = TechnicalReportSetting(
    section1DefaultTitle = section1DefaultTitle,
    section2DefaultTitle = section2DefaultTitle,
    section3DefaultTitle = section3DefaultTitle,
    section4DefaultTitle = section4DefaultTitle,
    introText = introText,
    finalText = finalText,
    reportPrefix = reportPrefix,
    nextReportNumber = nextReportNumber,
    numberLength = numberLength,
    allowManualNumber = allowManualNumber,
    nextNumberPreview = nextNumberPreview,
)

private fun String.toReportStatusLabel(): String = when (this) {
    "issued" -> "Emitido"
    "cancelled" -> "Anulado"
    else -> "Borrador"
}
