package com.facturador.facturapro.domain.model

data class DashboardSummary(
    val invoiceCount: Int,
    val clientCount: Int,
    val totalBilled: String,
    val totalBilledMonth: String,
    val monthlyTrend: Double?,
    val totalCollected: String,
    val collectionRate: Double,
    val pendingBalance: String,
    val pendingCount: Int,
    val overdueCount: Int,
    val currencySymbol: String,
    val monthlySeries: List<MonthlyPoint>,
    val statusChart: List<StatusSlice>,
    val recentInvoices: List<RecentInvoice>,
)

data class MonthlyPoint(
    val label: String,
    val value: Double,
)

data class StatusSlice(
    val status: String,
    val label: String,
    val count: Int,
)

data class RecentInvoice(
    val id: Long,
    val invoiceNumber: String?,
    val clientName: String,
    val invoiceDate: String,
    val status: String,
    val currencySymbol: String,
    val total: String,
)
