package com.facturador.facturapro.domain.model

data class ReportFilters(
    val dateFrom: String? = null,
    val dateTo: String? = null,
    val currencyCode: String? = null,
)

data class OperationalReport(
    val filters: ReportFilters,
    val overview: ReportOverview,
    val totals: ReportMoneyRow?,
    val totalsByCurrency: List<ReportMoneyRow>,
    val byDate: List<ReportDateRow>,
    val byStatus: List<ReportStatusRow>,
    val byClient: List<ReportClientRow>,
    val overdueInvoices: List<ReportOverdueInvoice>,
    val canShowUnifiedMoneyTotals: Boolean,
)

data class ReportOverview(
    val invoicesCount: Int,
    val overdueCount: Int,
)

data class ReportMoneyRow(
    val currencyCode: String,
    val currencySymbol: String,
    val invoicesCount: Int,
    val totalInvoiced: String,
    val totalCollected: String,
    val totalPending: String,
)

data class ReportDateRow(
    val invoiceDay: String,
    val currencyCode: String,
    val currencySymbol: String,
    val invoicesCount: Int,
    val totalInvoiced: String,
    val totalCollected: String,
    val totalPending: String,
)

data class ReportStatusRow(
    val status: String,
    val currencyCode: String,
    val currencySymbol: String,
    val invoicesCount: Int,
    val totalInvoiced: String,
    val totalCollected: String,
    val totalPending: String,
)

data class ReportClientRow(
    val clientName: String,
    val currencyCode: String,
    val currencySymbol: String,
    val invoicesCount: Int,
    val totalInvoiced: String,
    val totalCollected: String,
    val totalPending: String,
)

data class ReportOverdueInvoice(
    val id: Long,
    val invoiceNumber: String?,
    val invoiceDate: String?,
    val dueDate: String?,
    val clientName: String,
    val currencyCode: String,
    val currencySymbol: String,
    val total: String,
    val balanceDue: String,
    val status: String,
)
