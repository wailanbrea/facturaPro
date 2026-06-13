package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.DashboardSummary
import com.facturador.facturapro.domain.model.MonthlyPoint
import com.facturador.facturapro.domain.model.RecentInvoice
import com.facturador.facturapro.domain.model.StatusSlice
import com.google.gson.annotations.SerializedName

data class DashboardResponseDto(
    @SerializedName("invoice_count") val invoiceCount: Int = 0,
    @SerializedName("client_count") val clientCount: Int = 0,
    @SerializedName("total_billed") val totalBilled: String = "0",
    @SerializedName("total_billed_month") val totalBilledMonth: String = "0",
    @SerializedName("monthly_trend") val monthlyTrend: Double? = null,
    @SerializedName("total_collected") val totalCollected: String = "0",
    @SerializedName("collection_rate") val collectionRate: Double = 0.0,
    @SerializedName("pending_balance") val pendingBalance: String = "0",
    @SerializedName("pending_count") val pendingCount: Int = 0,
    @SerializedName("overdue_count") val overdueCount: Int = 0,
    @SerializedName("currency_symbol") val currencySymbol: String? = null,
    @SerializedName("monthly_series") val monthlySeries: List<MonthlyPointDto> = emptyList(),
    @SerializedName("status_chart") val statusChart: List<StatusSliceDto> = emptyList(),
    @SerializedName("recent_invoices") val recentInvoices: List<RecentInvoiceDto> = emptyList(),
)

data class MonthlyPointDto(
    val label: String = "",
    val value: String = "0",
)

data class StatusSliceDto(
    val status: String = "",
    val label: String = "",
    val count: Int = 0,
)

data class RecentInvoiceDto(
    val id: Long = 0,
    @SerializedName("invoice_number") val invoiceNumber: String? = null,
    @SerializedName("client_name") val clientName: String? = null,
    @SerializedName("invoice_date") val invoiceDate: String? = null,
    val status: String = "draft",
    @SerializedName("currency_symbol") val currencySymbol: String? = null,
    val total: String = "0",
)

fun DashboardResponseDto.toDomain(): DashboardSummary {
    val symbol = currencySymbol.orEmpty().ifBlank { "$" }

    return DashboardSummary(
        invoiceCount = invoiceCount,
        clientCount = clientCount,
        totalBilled = totalBilled,
        totalBilledMonth = totalBilledMonth,
        monthlyTrend = monthlyTrend,
        totalCollected = totalCollected,
        collectionRate = collectionRate,
        pendingBalance = pendingBalance,
        pendingCount = pendingCount,
        overdueCount = overdueCount,
        currencySymbol = symbol,
        monthlySeries = monthlySeries.map {
            MonthlyPoint(label = it.label, value = it.value.toDoubleOrNull() ?: 0.0)
        },
        statusChart = statusChart.map {
            StatusSlice(status = it.status, label = it.label, count = it.count)
        },
        recentInvoices = recentInvoices.map {
            RecentInvoice(
                id = it.id,
                invoiceNumber = it.invoiceNumber,
                clientName = it.clientName.orEmpty(),
                invoiceDate = it.invoiceDate.orEmpty(),
                status = it.status,
                currencySymbol = it.currencySymbol.orEmpty().ifBlank { symbol },
                total = it.total,
            )
        },
    )
}
