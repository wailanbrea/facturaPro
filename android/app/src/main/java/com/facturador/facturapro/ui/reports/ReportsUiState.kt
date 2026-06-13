package com.facturador.facturapro.ui.reports

import com.facturador.facturapro.domain.model.OperationalReport
import com.facturador.facturapro.domain.model.ReportFilters

data class ReportsUiState(
    val report: OperationalReport? = null,
    val filters: ReportFilters = ReportFilters(),
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
)
