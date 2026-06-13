package com.facturador.facturapro.ui.technicalreports

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.TechnicalReportRepositoryContract
import com.facturador.facturapro.domain.model.TechnicalReportDetail
import com.facturador.facturapro.domain.model.TechnicalReportDraft
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class TechnicalReportsViewModel(
    private val repository: TechnicalReportRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(TechnicalReportsUiState())
    val uiState: StateFlow<TechnicalReportsUiState> = _uiState.asStateFlow()

    init {
        loadSettings()
        refresh()
    }

    fun onSearchChanged(value: String) {
        _uiState.update { it.copy(searchQuery = value, errorMessage = null) }
    }

    fun refresh() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            repository.list(_uiState.value.searchQuery).fold(
                onSuccess = { reports ->
                    _uiState.update { it.copy(reports = reports, isLoading = false) }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudieron cargar los informes.",
                        )
                    }
                },
            )
        }
    }

    fun loadSettings() {
        viewModelScope.launch {
            repository.settings().fold(
                onSuccess = { setting -> _uiState.update { it.copy(setting = setting) } },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(errorMessage = error.message ?: "No se pudo cargar la configuracion de informes.")
                    }
                },
            )
        }
    }

    fun loadDetail(reportId: Long) {
        viewModelScope.launch {
            _uiState.update { it.copy(isDetailLoading = true, errorMessage = null, previewHtml = null, internalPdfPath = null) }
            repository.detail(reportId).fold(
                onSuccess = { report ->
                    _uiState.update { it.copy(selectedReport = report, isDetailLoading = false) }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isDetailLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar el informe.",
                        )
                    }
                },
            )
        }
    }

    fun clearSelection() {
        _uiState.update {
            it.copy(
                selectedReport = null,
                previewHtml = null,
                internalPdfPath = null,
                errorMessage = null,
            )
        }
    }

    fun createReport(draft: TechnicalReportDraft) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null, savedReportId = null) }
            repository.create(draft).fold(
                onSuccess = { report ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            savedReportId = report.id,
                            selectedReport = report,
                            reports = (it.reports.filterNot { current -> current.id == report.id } + report.toSummary())
                                .sortedByDescending { current -> current.reportDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo crear el informe.",
                        )
                    }
                },
            )
        }
    }

    fun updateReport(reportId: Long, draft: TechnicalReportDraft) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null) }
            repository.update(reportId, draft).fold(
                onSuccess = { report ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            selectedReport = report,
                            reports = (it.reports.filterNot { current -> current.id == report.id } + report.toSummary())
                                .sortedByDescending { current -> current.reportDate },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo actualizar el informe.",
                        )
                    }
                },
            )
        }
    }

    fun deleteOrCancelSelectedReport() {
        val reportId = _uiState.value.selectedReport?.id ?: return

        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null) }
            repository.deleteOrCancel(reportId).fold(
                onSuccess = {
                    _uiState.update { state ->
                        state.copy(
                            isSaving = false,
                            selectedReport = null,
                            reports = state.reports.filterNot { it.id == reportId },
                        )
                    }
                    refresh()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo eliminar o anular el informe.",
                        )
                    }
                },
            )
        }
    }

    fun loadPreviewForSelectedReport() {
        val reportId = _uiState.value.selectedReport?.id ?: return

        viewModelScope.launch {
            _uiState.update { it.copy(isPreviewLoading = true, previewHtml = null, internalPdfPath = null, errorMessage = null) }
            repository.preview(reportId).fold(
                onSuccess = { html ->
                    _uiState.update { it.copy(isPreviewLoading = false, previewHtml = html) }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isPreviewLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la vista previa.",
                        )
                    }
                },
            )
        }
    }

    fun clearPreview() {
        _uiState.update { it.copy(previewHtml = null, isPreviewLoading = false) }
    }

    fun generateAndViewPdfForSelectedReport() {
        val report = _uiState.value.selectedReport ?: return

        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, internalPdfPath = null, errorMessage = null) }
            runCatching {
                repository.generatePdf(report.id).getOrThrow()
                val fileName = "${report.reportNumber}.pdf"
                repository.downloadPdf(report.id, fileName).getOrThrow()
            }.fold(
                onSuccess = { file ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            internalPdfPath = file.absolutePath,
                        )
                    }
                    loadDetail(report.id)
                    refresh()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo generar o abrir el PDF.",
                        )
                    }
                },
            )
        }
    }

    fun clearInternalPdfViewer() {
        _uiState.update { it.copy(internalPdfPath = null) }
    }

    fun consumeSavedEvent() {
        _uiState.update { it.copy(savedReportId = null) }
    }

    companion object {
        fun factory(repository: TechnicalReportRepositoryContract): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    require(modelClass.isAssignableFrom(TechnicalReportsViewModel::class.java)) {
                        "Unknown ViewModel class: ${modelClass.name}"
                    }

                    return TechnicalReportsViewModel(repository) as T
                }
            }
    }
}

private fun TechnicalReportDetail.toSummary() = com.facturador.facturapro.domain.model.TechnicalReportSummary(
    id = id,
    reportNumber = reportNumber,
    reportDate = reportDate,
    recipientName = recipientName,
    recipientAddress = recipientAddress,
    sellerName = sellerName,
    status = status,
    statusLabel = statusLabel,
    pdfPath = pdfPath,
)
