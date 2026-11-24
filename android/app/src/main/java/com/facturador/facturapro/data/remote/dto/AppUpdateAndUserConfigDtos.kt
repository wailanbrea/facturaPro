package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.AppReleaseInfo
import com.facturador.facturapro.domain.model.AssignedCompanyInfo
import com.facturador.facturapro.domain.model.CalendarConfigSection
import com.facturador.facturapro.domain.model.ConfigBankAccountItem
import com.facturador.facturapro.domain.model.ConfigCurrencyItem
import com.facturador.facturapro.domain.model.ConfigLegalTextItem
import com.facturador.facturapro.domain.model.ConfigPaymentTermItem
import com.facturador.facturapro.domain.model.ConfigTaxItem
import com.facturador.facturapro.domain.model.ConfigWarrantyItem
import com.facturador.facturapro.domain.model.CurrentUserConfig
import com.facturador.facturapro.domain.model.InvoicingConfigSection
import com.facturador.facturapro.domain.model.PermissionGroupInfo
import com.facturador.facturapro.domain.model.PermissionItemInfo
import com.facturador.facturapro.domain.model.SystemRoleSummary
import com.facturador.facturapro.domain.model.SystemUserSummary
import com.facturador.facturapro.domain.model.SystemUsersConfigSection
import com.facturador.facturapro.domain.model.TechnicalReportsConfigSection
import com.facturador.facturapro.domain.model.UserConfigurationOverview
import com.facturador.facturapro.domain.model.UserRoleInfo
import com.google.gson.annotations.SerializedName

data class AppReleaseDto(
    @SerializedName("version_code") val versionCode: Int = 1,
    @SerializedName("version_name") val versionName: String = "1.0",
    @SerializedName("download_url") val downloadUrl: String = "",
    @SerializedName("release_notes") val releaseNotes: String = "",
    @SerializedName("published_at") val publishedAt: String = "",
    val source: String = "manifest",
)

fun AppReleaseDto.toDomain(): AppReleaseInfo = AppReleaseInfo(
    versionCode = versionCode,
    versionName = versionName,
    downloadUrl = downloadUrl,
    releaseNotes = releaseNotes,
    publishedAt = publishedAt,
    source = source,
)

data class UserRoleDto(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String = "",
)

data class PermissionItemDto(
    val slug: String,
    val name: String,
)

data class PermissionGroupDto(
    val group: String,
    val permissions: List<PermissionItemDto> = emptyList(),
)

data class AssignedCompanyDto(
    val id: Long,
    val name: String,
    @SerializedName("tax_id") val taxId: String = "",
    val city: String = "",
    @SerializedName("is_default") val isDefault: Boolean = false,
    @SerializedName("logos_count") val logosCount: Int = 0,
    @SerializedName("next_invoice_number") val nextInvoiceNumber: String? = null,
    @SerializedName("next_quotation_number") val nextQuotationNumber: String? = null,
)

data class CurrentUserConfigDto(
    val id: Long,
    val name: String,
    val email: String,
    val roles: List<UserRoleDto> = emptyList(),
    @SerializedName("permission_groups") val permissionGroups: List<PermissionGroupDto> = emptyList(),
    @SerializedName("permissions_count") val permissionsCount: Int = 0,
    @SerializedName("has_all_companies_access") val hasAllCompaniesAccess: Boolean = false,
    @SerializedName("assigned_companies") val assignedCompanies: List<AssignedCompanyDto> = emptyList(),
    @SerializedName("push_devices_count") val pushDevicesCount: Int = 0,
)

data class ConfigCurrencyDto(
    val code: String,
    val name: String,
    val symbol: String,
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class ConfigTaxDto(
    val name: String,
    val rate: String,
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class ConfigPaymentTermDto(
    val name: String,
    val days: Int,
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class ConfigWarrantyDto(
    val title: String,
    @SerializedName("duration_months") val durationMonths: Int,
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class ConfigBankAccountDto(
    val label: String,
    @SerializedName("bank_name") val bankName: String = "",
    @SerializedName("account_holder") val accountHolder: String = "",
    @SerializedName("account_type") val accountType: String = "official",
    @SerializedName("currency_code") val currencyCode: String = "",
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class ConfigLegalTextDto(
    val name: String,
    @SerializedName("is_default") val isDefault: Boolean = false,
)

data class InvoicingConfigSectionDto(
    val currencies: List<ConfigCurrencyDto> = emptyList(),
    val taxes: List<ConfigTaxDto> = emptyList(),
    @SerializedName("payment_terms") val paymentTerms: List<ConfigPaymentTermDto> = emptyList(),
    val warranties: List<ConfigWarrantyDto> = emptyList(),
    @SerializedName("bank_accounts") val bankAccounts: List<ConfigBankAccountDto> = emptyList(),
    @SerializedName("legal_texts") val legalTexts: List<ConfigLegalTextDto> = emptyList(),
    @SerializedName("effective_locked_fields") val effectiveLockedFields: List<String> = emptyList(),
    @SerializedName("system_locked_fields") val systemLockedFields: List<String>? = null,
)

data class TechnicalReportsConfigSectionDto(
    @SerializedName("report_prefix") val reportPrefix: String = "",
    @SerializedName("next_number_preview") val nextNumberPreview: String = "",
    @SerializedName("section_titles") val sectionTitles: List<String> = emptyList(),
)

data class CalendarConfigSectionDto(
    @SerializedName("can_view_calendar") val canViewCalendar: Boolean = false,
    @SerializedName("can_manage_appointments") val canManageAppointments: Boolean = false,
    @SerializedName("registered_push_devices") val registeredPushDevices: Int = 0,
)

data class SystemUserSummaryDto(
    val id: Long,
    val name: String,
    val email: String,
    val roles: List<String> = emptyList(),
    @SerializedName("assigned_companies") val assignedCompanies: List<String> = emptyList(),
    @SerializedName("invoices_count") val invoicesCount: Int = 0,
    @SerializedName("reports_count") val reportsCount: Int = 0,
    @SerializedName("appointments_count") val appointmentsCount: Int = 0,
)

data class SystemRoleSummaryDto(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String = "",
    @SerializedName("users_count") val usersCount: Int = 0,
    @SerializedName("permissions_count") val permissionsCount: Int = 0,
    @SerializedName("permission_names") val permissionNames: List<String> = emptyList(),
)

data class SystemUsersConfigSectionDto(
    val users: List<SystemUserSummaryDto> = emptyList(),
    val roles: List<SystemRoleSummaryDto> = emptyList(),
)

data class UserConfigurationOverviewDto(
    @SerializedName("current_user") val currentUser: CurrentUserConfigDto,
    @SerializedName("invoicing_config") val invoicingConfig: InvoicingConfigSectionDto? = null,
    @SerializedName("technical_reports_config") val technicalReportsConfig: TechnicalReportsConfigSectionDto? = null,
    @SerializedName("calendar_config") val calendarConfig: CalendarConfigSectionDto? = null,
    @SerializedName("system_users_config") val systemUsersConfig: SystemUsersConfigSectionDto? = null,
)

fun UserConfigurationOverviewDto.toDomain(): UserConfigurationOverview = UserConfigurationOverview(
    currentUser = CurrentUserConfig(
        id = currentUser.id,
        name = currentUser.name,
        email = currentUser.email,
        roles = currentUser.roles.map { UserRoleInfo(it.id, it.name, it.slug, it.description) },
        permissionGroups = currentUser.permissionGroups.map { grp ->
            PermissionGroupInfo(
                group = grp.group,
                permissions = grp.permissions.map { PermissionItemInfo(it.slug, it.name) },
            )
        },
        permissionsCount = currentUser.permissionsCount,
        hasAllCompaniesAccess = currentUser.hasAllCompaniesAccess,
        assignedCompanies = currentUser.assignedCompanies.map {
            AssignedCompanyInfo(
                id = it.id,
                name = it.name,
                taxId = it.taxId,
                city = it.city,
                isDefault = it.isDefault,
                logosCount = it.logosCount,
                nextInvoiceNumber = it.nextInvoiceNumber,
                nextQuotationNumber = it.nextQuotationNumber,
            )
        },
        pushDevicesCount = currentUser.pushDevicesCount,
    ),
    invoicingConfig = invoicingConfig?.let { inv ->
        InvoicingConfigSection(
            currencies = inv.currencies.map { ConfigCurrencyItem(it.code, it.name, it.symbol, it.isDefault) },
            taxes = inv.taxes.map { ConfigTaxItem(it.name, it.rate, it.isDefault) },
            paymentTerms = inv.paymentTerms.map { ConfigPaymentTermItem(it.name, it.days, it.isDefault) },
            warranties = inv.warranties.map { ConfigWarrantyItem(it.title, it.durationMonths, it.isDefault) },
            bankAccounts = inv.bankAccounts.map {
                ConfigBankAccountItem(it.label, it.bankName, it.accountHolder, it.accountType, it.currencyCode, it.isDefault)
            },
            legalTexts = inv.legalTexts.map { ConfigLegalTextItem(it.name, it.isDefault) },
            effectiveLockedFields = inv.effectiveLockedFields,
            systemLockedFields = inv.systemLockedFields,
        )
    },
    technicalReportsConfig = technicalReportsConfig?.let { rep ->
        TechnicalReportsConfigSection(
            reportPrefix = rep.reportPrefix,
            nextNumberPreview = rep.nextNumberPreview,
            sectionTitles = rep.sectionTitles,
        )
    },
    calendarConfig = calendarConfig?.let { cal ->
        CalendarConfigSection(
            canViewCalendar = cal.canViewCalendar,
            canManageAppointments = cal.canManageAppointments,
            registeredPushDevices = cal.registeredPushDevices,
        )
    },
    systemUsersConfig = systemUsersConfig?.let { sys ->
        SystemUsersConfigSection(
            users = sys.users.map {
                SystemUserSummary(
                    id = it.id,
                    name = it.name,
                    email = it.email,
                    roles = it.roles,
                    assignedCompanies = it.assignedCompanies,
                    invoicesCount = it.invoicesCount,
                    reportsCount = it.reportsCount,
                    appointmentsCount = it.appointmentsCount,
                )
            },
            roles = sys.roles.map {
                SystemRoleSummary(
                    id = it.id,
                    name = it.name,
                    slug = it.slug,
                    description = it.description,
                    usersCount = it.usersCount,
                    permissionsCount = it.permissionsCount,
                    permissionNames = it.permissionNames,
                )
            },
        )
    },
)
