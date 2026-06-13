package com.facturador.facturapro.ui.dashboard

import com.facturador.facturapro.domain.model.DashboardSummary

data class DashboardUiState(
    val isLoading: Boolean = false,
    val summary: DashboardSummary? = null,
    val errorMessage: String? = null,
)
