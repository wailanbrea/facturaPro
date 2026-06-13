package com.facturador.facturapro.ui.reports

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.ReportRepositoryContract
import com.facturador.facturapro.domain.model.ReportFilters
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class ReportsViewModel(
    private val repository: ReportRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(ReportsUiState())
    val uiState: StateFlow<ReportsUiState> = _uiState.asStateFlow()

    init {
        refresh()
    }

    fun onDateFromChanged(value: String) {
        _uiState.update { it.copy(filters = it.filters.copy(dateFrom = value.ifBlank { null }), errorMessage = null) }
    }

    fun onDateToChanged(value: String) {
        _uiState.update { it.copy(filters = it.filters.copy(dateTo = value.ifBlank { null }), errorMessage = null) }
    }

    fun clearFilters() {
        _uiState.update { it.copy(filters = ReportFilters(), errorMessage = null) }
        refresh(ReportFilters())
    }

    fun refresh() {
        refresh(_uiState.value.filters)
    }

    private fun refresh(filters: ReportFilters) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            repository.load(filters).fold(
                onSuccess = { report ->
                    _uiState.update {
                        it.copy(
                            report = report,
                            filters = report.filters,
                            isLoading = false,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar reportes.",
                        )
                    }
                },
            )
        }
    }

    companion object {
        fun factory(repository: ReportRepositoryContract): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(ReportsViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return ReportsViewModel(repository) as T
            }
        }
    }
}
