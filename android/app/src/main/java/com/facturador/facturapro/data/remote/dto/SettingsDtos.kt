package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.BankAccountCatalogItem
import com.facturador.facturapro.domain.model.CurrencyCatalogItem
import com.facturador.facturapro.domain.model.FiscalProfileCatalogItem
import com.facturador.facturapro.domain.model.FiscalProfileLogoCatalogItem
import com.facturador.facturapro.domain.model.LegalTextCatalogItem
import com.facturador.facturapro.domain.model.PaymentTermCatalogItem
import com.facturador.facturapro.domain.model.TaxCatalogItem
import com.facturador.facturapro.domain.model.WarrantyCatalogItem
import com.google.gson.annotations.SerializedName

data class BootstrapResponseDto(
    val data: BootstrapDto,
)

data class BootstrapDto(
    val currencies: List<CurrencyDto> = emptyList(),
    val taxes: List<TaxDto> = emptyList(),
    @SerializedName("payment_terms")
    val paymentTerms: List<PaymentTermDto> = emptyList(),
    val warranties: List<WarrantyDto> = emptyList(),
    @SerializedName("bank_accounts")
    val bankAccounts: List<NamedDto> = emptyList(),
    @SerializedName("fiscal_profiles")
    val fiscalProfiles: List<FiscalProfileDto> = emptyList(),
    @SerializedName("legal_texts")
    val legalTexts: List<LegalTextDto> = emptyList(),
    @SerializedName("invoice_number_settings")
    val invoiceNumberSettings: List<InvoiceNumberSettingDto> = emptyList(),
)

data class CurrencyDto(
    val id: Long,
    val code: String,
    val name: String,
    val symbol: String,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class TaxDto(
    val id: Long,
    val name: String,
    val rate: String,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class PaymentTermDto(
    val id: Long,
    val name: String,
    val days: Int,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class WarrantyDto(
    val id: Long,
    val title: String,
    @SerializedName("duration_months")
    val durationMonths: Int = 0,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class NamedDto(
    val id: Long,
    val name: String? = null,
    val label: String? = null,
    @SerializedName("account_type")
    val accountType: String? = null,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class FiscalProfileDto(
    val id: Long,
    val name: String? = null,
    val label: String? = null,
    @SerializedName("logo_path")
    val logoPath: String? = null,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
    val logos: List<FiscalProfileLogoDto> = emptyList(),
)

data class FiscalProfileLogoDto(
    val path: String,
    val label: String? = null,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

data class InvoiceNumberSettingDto(
    @SerializedName("fiscal_profile_id")
    val fiscalProfileId: Long? = null,
    @SerializedName("document_type")
    val documentType: String = "invoice",
    val prefix: String = "",
    val serie: String? = null,
    @SerializedName("next_number")
    val nextNumber: Int = 1,
    @SerializedName("number_length")
    val numberLength: Int = 6,
)

data class LegalTextDto(
    val id: Long,
    val name: String,
    @SerializedName("legal_footer")
    val legalFooter: String,
    @SerializedName("warranty_text")
    val warrantyText: String,
    @SerializedName("conformity_text")
    val conformityText: String? = null,
    @SerializedName("is_default")
    val isDefault: Boolean = false,
)

fun BootstrapDto.toDomain(): BootstrapCatalogs = BootstrapCatalogs(
    currencies = currencies.map {
        CurrencyCatalogItem(
            id = it.id,
            code = it.code,
            name = it.name,
            symbol = it.symbol,
            isDefault = it.isDefault,
        )
    },
    taxes = taxes.map {
        TaxCatalogItem(
            id = it.id,
            name = it.name,
            rate = it.rate,
            isDefault = it.isDefault,
        )
    },
    paymentTerms = paymentTerms.map {
        PaymentTermCatalogItem(
            id = it.id,
            name = it.name,
            days = it.days,
            isDefault = it.isDefault,
        )
    },
    warranties = warranties.map {
        WarrantyCatalogItem(
            id = it.id,
            title = it.title,
            durationMonths = it.durationMonths,
            isDefault = it.isDefault,
        )
    },
    bankAccounts = bankAccounts.map {
        BankAccountCatalogItem(
            id = it.id,
            name = it.label ?: it.name.orEmpty(),
            accountType = it.accountType ?: "official",
            isDefault = it.isDefault,
        )
    },
    fiscalProfiles = fiscalProfiles.map { profile ->
        FiscalProfileCatalogItem(
            id = profile.id,
            name = profile.name ?: profile.label.orEmpty(),
            isDefault = profile.isDefault,
            logoPath = profile.logoPath,
            logos = profile.logos.map { logo ->
                FiscalProfileLogoCatalogItem(
                    path = logo.path,
                    label = logo.label ?: logo.path.substringAfterLast('/'),
                    isDefault = logo.isDefault,
                )
            },
            nextInvoiceNumber = invoiceNumberSettings.firstOrNull {
                it.fiscalProfileId == profile.id && it.documentType == "invoice"
            }?.previewNumber(),
            nextQuotationNumber = invoiceNumberSettings.firstOrNull {
                it.fiscalProfileId == profile.id && it.documentType == "quotation"
            }?.previewNumber(),
        )
    },
    legalTexts = legalTexts.map {
        LegalTextCatalogItem(
            id = it.id,
            name = it.name,
            legalFooter = it.legalFooter,
            warrantyText = it.warrantyText,
            conformityText = it.conformityText.orEmpty(),
            isDefault = it.isDefault,
        )
    },
)

private fun InvoiceNumberSettingDto.previewNumber(): String {
    val seriePart = serie.orEmpty()
    val padded = nextNumber.toString().padStart(numberLength, '0')

    return "$prefix$seriePart-$padded"
}
