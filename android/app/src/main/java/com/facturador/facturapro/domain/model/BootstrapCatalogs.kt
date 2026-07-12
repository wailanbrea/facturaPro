package com.facturador.facturapro.domain.model

data class BootstrapCatalogs(
    val currencies: List<CurrencyCatalogItem>,
    val taxes: List<TaxCatalogItem>,
    val paymentTerms: List<PaymentTermCatalogItem>,
    val warranties: List<WarrantyCatalogItem>,
    val bankAccounts: List<BankAccountCatalogItem>,
    val fiscalProfiles: List<FiscalProfileCatalogItem>,
    val legalTexts: List<LegalTextCatalogItem>,
)

data class CurrencyCatalogItem(
    val id: Long,
    val code: String,
    val name: String,
    val symbol: String,
    val isDefault: Boolean,
)

data class TaxCatalogItem(
    val id: Long,
    val name: String,
    val rate: String,
    val isDefault: Boolean,
)

data class PaymentTermCatalogItem(
    val id: Long,
    val name: String,
    val days: Int,
    val isDefault: Boolean,
)

data class WarrantyCatalogItem(
    val id: Long,
    val title: String,
    val durationMonths: Int,
    val isDefault: Boolean,
)

data class NamedCatalogItem(
    val id: Long,
    val name: String,
    val isDefault: Boolean,
)

data class FiscalProfileCatalogItem(
    val id: Long,
    val name: String,
    val isDefault: Boolean,
    val logoPath: String?,
    val logos: List<FiscalProfileLogoCatalogItem>,
    val nextInvoiceNumber: String?,
    val nextQuotationNumber: String?,
)

data class FiscalProfileLogoCatalogItem(
    val path: String,
    val label: String,
    val isDefault: Boolean,
)

data class BankAccountCatalogItem(
    val id: Long,
    val name: String,
    val accountType: String,
    val isDefault: Boolean,
)

data class LegalTextCatalogItem(
    val id: Long,
    val name: String,
    val legalFooter: String,
    val warrantyText: String,
    val conformityText: String,
    val isDefault: Boolean,
)
