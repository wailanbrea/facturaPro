package com.facturador.facturapro.ui.technicalreports

import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportSetting
import com.facturador.facturapro.domain.model.TechnicalReportSummary

data class TechnicalReportsUiState(
    val reports: List<TechnicalReportSummary> = emptyList(),
    val searchQuery: String = "",
    val setting: TechnicalReportSetting? = null,
    val selectedReport: TechnicalReportDetail? = null,
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isDetailLoading: Boolean = false,
    val isPreviewLoading: Boolean = false,
    val previewHtml: String? = null,
    val internalPdfPath: String? = null,
    val errorMessage: String? = null,
    val savedReportId: Long? = null,
)
