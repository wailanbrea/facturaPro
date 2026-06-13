package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.OperationalReport
import com.facturador.facturapro.domain.model.ReportClientRow
import com.facturador.facturapro.domain.model.ReportDateRow
import com.facturador.facturapro.domain.model.ReportFilters
import com.facturador.facturapro.domain.model.ReportMoneyRow
import com.facturador.facturapro.domain.model.ReportOverview
import com.facturador.facturapro.domain.model.ReportOverdueInvoice
import com.facturador.facturapro.domain.model.ReportStatusRow
import com.google.gson.annotations.SerializedName

data class ReportResponseDto(
    val data: OperationalReportDto,
)

data class OperationalReportDto(
    val filters: ReportFiltersDto,
    val overview: ReportOverviewDto,
    val totals: ReportMoneyRowDto?,
    @SerializedName("totals_by_currency")
    val totalsByCurrency: List<ReportMoneyRowDto> = emptyList(),
    @SerializedName("by_date")
    val byDate: List<ReportDateRowDto> = emptyList(),
    @SerializedName("by_status")
    val byStatus: List<ReportStatusRowDto> = emptyList(),
    @SerializedName("by_client")
    val byClient: List<ReportClientRowDto> = emptyList(),
    @SerializedName("overdue_invoices")
    val overdueInvoices: List<ReportOverdueInvoiceDto> = emptyList(),
    @SerializedName("can_show_unified_money_totals")
    val canShowUnifiedMoneyTotals: Boolean,
)

data class ReportFiltersDto(
    @SerializedName("date_from")
    val dateFrom: String?,
    @SerializedName("date_to")
    val dateTo: String?,
    @SerializedName("currency_code")
    val currencyCode: String?,
)

data class ReportOverviewDto(
    @SerializedName("invoices_count")
    val invoicesCount: Int,
    @SerializedName("overdue_count")
    val overdueCount: Int,
)

data class ReportMoneyRowDto(
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    @SerializedName("invoices_count")
    val invoicesCount: Int,
    @SerializedName("total_invoiced")
    val totalInvoiced: String,
    @SerializedName("total_collected")
    val totalCollected: String,
    @SerializedName("total_pending")
    val totalPending: String,
)

data class ReportDateRowDto(
    @SerializedName("invoice_day")
    val invoiceDay: String,
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    @SerializedName("invoices_count")
    val invoicesCount: Int,
    @SerializedName("total_invoiced")
    val totalInvoiced: String,
    @SerializedName("total_collected")
    val totalCollected: String,
    @SerializedName("total_pending")
    val totalPending: String,
)

data class ReportStatusRowDto(
    val status: String,
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    @SerializedName("invoices_count")
    val invoicesCount: Int,
    @SerializedName("total_invoiced")
    val totalInvoiced: String,
    @SerializedName("total_collected")
    val totalCollected: String,
    @SerializedName("total_pending")
    val totalPending: String,
)

data class ReportClientRowDto(
    @SerializedName("client_name")
    val clientName: String,
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    @SerializedName("invoices_count")
    val invoicesCount: Int,
    @SerializedName("total_invoiced")
    val totalInvoiced: String,
    @SerializedName("total_collected")
    val totalCollected: String,
    @SerializedName("total_pending")
    val totalPending: String,
)

data class ReportOverdueInvoiceDto(
    val id: Long,
    @SerializedName("invoice_number")
    val invoiceNumber: String?,
    @SerializedName("invoice_date")
    val invoiceDate: String?,
    @SerializedName("due_date")
    val dueDate: String?,
    @SerializedName("client_name")
    val clientName: String,
    @SerializedName("currency_code")
    val currencyCode: String,
    @SerializedName("currency_symbol")
    val currencySymbol: String,
    val total: String,
    @SerializedName("balance_due")
    val balanceDue: String,
    val status: String,
)

fun OperationalReportDto.toDomain(): OperationalReport = OperationalReport(
    filters = filters.toDomain(),
    overview = overview.toDomain(),
    totals = totals?.toDomain(),
    totalsByCurrency = totalsByCurrency.map(ReportMoneyRowDto::toDomain),
    byDate = byDate.map(ReportDateRowDto::toDomain),
    byStatus = byStatus.map(ReportStatusRowDto::toDomain),
    byClient = byClient.map(ReportClientRowDto::toDomain),
    overdueInvoices = overdueInvoices.map(ReportOverdueInvoiceDto::toDomain),
    canShowUnifiedMoneyTotals = canShowUnifiedMoneyTotals,
)

private fun ReportFiltersDto.toDomain(): ReportFilters = ReportFilters(
    dateFrom = dateFrom,
    dateTo = dateTo,
    currencyCode = currencyCode,
)

private fun ReportOverviewDto.toDomain(): ReportOverview = ReportOverview(
    invoicesCount = invoicesCount,
    overdueCount = overdueCount,
)

private fun ReportMoneyRowDto.toDomain(): ReportMoneyRow = ReportMoneyRow(
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    invoicesCount = invoicesCount,
    totalInvoiced = totalInvoiced,
    totalCollected = totalCollected,
    totalPending = totalPending,
)

private fun ReportDateRowDto.toDomain(): ReportDateRow = ReportDateRow(
    invoiceDay = invoiceDay,
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    invoicesCount = invoicesCount,
    totalInvoiced = totalInvoiced,
    totalCollected = totalCollected,
    totalPending = totalPending,
)

private fun ReportStatusRowDto.toDomain(): ReportStatusRow = ReportStatusRow(
    status = status,
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    invoicesCount = invoicesCount,
    totalInvoiced = totalInvoiced,
    totalCollected = totalCollected,
    totalPending = totalPending,
)

private fun ReportClientRowDto.toDomain(): ReportClientRow = ReportClientRow(
    clientName = clientName,
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    invoicesCount = invoicesCount,
    totalInvoiced = totalInvoiced,
    totalCollected = totalCollected,
    totalPending = totalPending,
)

private fun ReportOverdueInvoiceDto.toDomain(): ReportOverdueInvoice = ReportOverdueInvoice(
    id = id,
    invoiceNumber = invoiceNumber,
    invoiceDate = invoiceDate,
    dueDate = dueDate,
    clientName = clientName,
    currencyCode = currencyCode,
    currencySymbol = currencySymbol,
    total = total,
    balanceDue = balanceDue,
    status = status,
)
