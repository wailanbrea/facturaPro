package com.facturador.facturapro.domain.model

data class AppReleaseInfo(
    val versionCode: Int,
    val versionName: String,
    val downloadUrl: String,
    val releaseNotes: String,
    val publishedAt: String,
    val source: String,
)

data class UserRoleInfo(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String,
)

data class PermissionItemInfo(
    val slug: String,
    val name: String,
)

data class PermissionGroupInfo(
    val group: String,
    val permissions: List<PermissionItemInfo>,
)

data class AssignedCompanyInfo(
    val id: Long,
    val name: String,
    val taxId: String,
    val city: String,
    val isDefault: Boolean,
    val logosCount: Int,
    val nextInvoiceNumber: String?,
    val nextQuotationNumber: String?,
)

data class CurrentUserConfig(
    val id: Long,
    val name: String,
    val email: String,
    val roles: List<UserRoleInfo>,
    val permissionGroups: List<PermissionGroupInfo>,
    val permissionsCount: Int,
    val hasAllCompaniesAccess: Boolean,
    val assignedCompanies: List<AssignedCompanyInfo>,
    val pushDevicesCount: Int,
)

data class ConfigCurrencyItem(
    val code: String,
    val name: String,
    val symbol: String,
    val isDefault: Boolean,
)

data class ConfigTaxItem(
    val name: String,
    val rate: String,
    val isDefault: Boolean,
)

data class ConfigPaymentTermItem(
    val name: String,
    val days: Int,
    val isDefault: Boolean,
)

data class ConfigWarrantyItem(
    val title: String,
    val durationMonths: Int,
    val isDefault: Boolean,
)

data class ConfigBankAccountItem(
    val label: String,
    val bankName: String,
    val accountHolder: String,
    val accountType: String,
    val currencyCode: String,
    val isDefault: Boolean,
)

data class ConfigLegalTextItem(
    val name: String,
    val isDefault: Boolean,
)

data class InvoicingConfigSection(
    val currencies: List<ConfigCurrencyItem>,
    val taxes: List<ConfigTaxItem>,
    val paymentTerms: List<ConfigPaymentTermItem>,
    val warranties: List<ConfigWarrantyItem>,
    val bankAccounts: List<ConfigBankAccountItem>,
    val legalTexts: List<ConfigLegalTextItem>,
    val effectiveLockedFields: List<String>,
    val systemLockedFields: List<String>?,
)

data class TechnicalReportsConfigSection(
    val reportPrefix: String,
    val nextNumberPreview: String,
    val sectionTitles: List<String>,
)

data class CalendarConfigSection(
    val canViewCalendar: Boolean,
    val canManageAppointments: Boolean,
    val registeredPushDevices: Int,
)

data class SystemUserSummary(
    val id: Long,
    val name: String,
    val email: String,
    val roles: List<String>,
    val assignedCompanies: List<String>,
    val invoicesCount: Int,
    val reportsCount: Int,
    val appointmentsCount: Int,
)

data class SystemRoleSummary(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String,
    val usersCount: Int,
    val permissionsCount: Int,
    val permissionNames: List<String>,
)

data class SystemUsersConfigSection(
    val users: List<SystemUserSummary>,
    val roles: List<SystemRoleSummary>,
)

data class UserConfigurationOverview(
    val currentUser: CurrentUserConfig,
    val invoicingConfig: InvoicingConfigSection?,
    val technicalReportsConfig: TechnicalReportsConfigSection?,
    val calendarConfig: CalendarConfigSection?,
    val systemUsersConfig: SystemUsersConfigSection?,
)
