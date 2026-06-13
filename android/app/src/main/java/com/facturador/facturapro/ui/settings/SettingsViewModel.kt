package com.facturador.facturapro.ui.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.PrinterRepositoryContract
import com.facturador.facturapro.domain.model.PrinterDevice
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class SettingsViewModel(
    private val printerRepository: PrinterRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            printerRepository.selectedPrinter.collect { printer ->
                _uiState.update { it.copy(selectedPrinter = printer) }
            }
        }
    }

    fun loadBluetoothPrinters() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoadingPrinters = true, printerMessage = null) }
            printerRepository.pairedBluetoothPrinters().fold(
                onSuccess = { printers ->
                    _uiState.update {
                        it.copy(
                            availablePrinters = printers,
                            isLoadingPrinters = false,
                            printerMessage = if (printers.isEmpty()) {
                                "No hay impresoras Bluetooth vinculadas."
                            } else {
                                null
                            },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoadingPrinters = false,
                            printerMessage = error.message ?: "No se pudieron cargar las impresoras.",
                        )
                    }
                },
            )
        }
    }

    fun savePrinter(printer: PrinterDevice) {
        viewModelScope.launch {
            printerRepository.savePrinter(printer).fold(
                onSuccess = {
                    _uiState.update {
                        it.copy(printerMessage = "Impresora guardada.")
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(printerMessage = error.message ?: "No se pudo guardar la impresora.")
                    }
                },
            )
        }
    }

    fun clearPrinter() {
        viewModelScope.launch {
            printerRepository.clearPrinter().fold(
                onSuccess = {
                    _uiState.update {
                        it.copy(printerMessage = "Impresora eliminada.")
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(printerMessage = error.message ?: "No se pudo eliminar la impresora.")
                    }
                },
            )
        }
    }

    fun onBluetoothPermissionsDenied() {
        _uiState.update {
            it.copy(printerMessage = "Permiso Bluetooth denegado. No se pueden ver impresoras.")
        }
    }

    companion object {
        fun factory(printerRepository: PrinterRepositoryContract): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    require(modelClass.isAssignableFrom(SettingsViewModel::class.java)) {
                        "Unknown ViewModel class: ${modelClass.name}"
                    }

                    return SettingsViewModel(printerRepository) as T
                }
            }
    }
}
