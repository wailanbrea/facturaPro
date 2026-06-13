package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.BankAccountCatalogItem
import com.facturador.facturapro.domain.model.CurrencyCatalogItem
import com.facturador.facturapro.domain.model.LegalTextCatalogItem
import com.facturador.facturapro.domain.model.NamedCatalogItem
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
    val fiscalProfiles: List<NamedDto> = emptyList(),
    @SerializedName("legal_texts")
    val legalTexts: List<LegalTextDto> = emptyList(),
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
    fiscalProfiles = fiscalProfiles.map {
        NamedCatalogItem(
            id = it.id,
            name = it.name ?: it.label.orEmpty(),
            isDefault = it.isDefault,
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
