package com.facturador.facturapro.ui.verification

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.InvoiceRepositoryContract
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class VerificationViewModel(
    private val repository: InvoiceRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(VerificationUiState())
    val uiState: StateFlow<VerificationUiState> = _uiState.asStateFlow()

    fun onNumberChanged(value: String) {
        _uiState.update { it.copy(number = value, errorMessage = null) }
    }

    fun onCodeChanged(value: String) {
        _uiState.update { it.copy(code = value.uppercase(), errorMessage = null) }
    }

    /** Apply a scanned QR payload and verify immediately. */
    fun onScanned(credentials: ScannedCredentials) {
        _uiState.update {
            it.copy(number = credentials.number, code = credentials.code.uppercase(), errorMessage = null, result = null)
        }
        verify()
    }

    fun onScanFailed(message: String) {
        _uiState.update { it.copy(errorMessage = message) }
    }

    fun verify() {
        val number = _uiState.value.number.trim()
        val code = _uiState.value.code.trim()

        if (number.isEmpty() || code.isEmpty()) {
            _uiState.update { it.copy(errorMessage = "Ingresa el número y el código de seguridad.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null, result = null) }
            repository.verify(number, code).fold(
                onSuccess = { verification ->
                    _uiState.update { it.copy(isLoading = false, result = verification) }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudo verificar el documento.",
                        )
                    }
                },
            )
        }
    }

    fun reset() {
        _uiState.value = VerificationUiState()
    }

    companion object {
        fun factory(repository: InvoiceRepositoryContract): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(VerificationViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return VerificationViewModel(repository) as T
            }
        }
    }
}
