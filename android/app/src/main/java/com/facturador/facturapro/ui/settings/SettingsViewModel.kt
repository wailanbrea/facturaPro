package com.facturador.facturapro.ui.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.local.ServerConfigStoreContract
import com.facturador.facturapro.data.repository.PrinterRepositoryContract
import com.facturador.facturapro.domain.model.PrinterDevice
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class SettingsViewModel(
    private val printerRepository: PrinterRepositoryContract,
    private val serverConfigStore: ServerConfigStoreContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            serverConfigStore.apiBaseUrl.collect { apiBaseUrl ->
                _uiState.update {
                    it.copy(
                        currentApiBaseUrl = apiBaseUrl,
                        serverUrlInput = if (it.serverUrlInput.isBlank()) apiBaseUrl else it.serverUrlInput,
                    )
                }
            }
        }

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

    fun onServerUrlChanged(value: String) {
        _uiState.update {
            it.copy(serverUrlInput = value, serverMessage = null)
        }
    }

    fun saveServerUrl() {
        viewModelScope.launch {
            val rawValue = _uiState.value.serverUrlInput
            _uiState.update { it.copy(isSavingServerUrl = true, serverMessage = null) }

            serverConfigStore.saveApiBaseUrl(rawValue).fold(
                onSuccess = { normalized ->
                    _uiState.update {
                        it.copy(
                            currentApiBaseUrl = normalized,
                            serverUrlInput = normalized,
                            isSavingServerUrl = false,
                            serverMessage = "Servidor guardado. Si estabas conectado a otro servidor, cierra sesion e inicia de nuevo.",
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSavingServerUrl = false,
                            serverMessage = error.message ?: "No se pudo guardar el servidor.",
                        )
                    }
                },
            )
        }
    }

    fun resetServerUrl() {
        viewModelScope.launch {
            val defaultUrl = serverConfigStore.resetApiBaseUrl()
            _uiState.update {
                it.copy(
                    currentApiBaseUrl = defaultUrl,
                    serverUrlInput = defaultUrl,
                    serverMessage = "Servidor restaurado al valor predeterminado.",
                )
            }
        }
    }

    companion object {
        fun factory(
            printerRepository: PrinterRepositoryContract,
            serverConfigStore: ServerConfigStoreContract,
        ): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    require(modelClass.isAssignableFrom(SettingsViewModel::class.java)) {
                        "Unknown ViewModel class: ${modelClass.name}"
                    }

                    return SettingsViewModel(
                        printerRepository = printerRepository,
                        serverConfigStore = serverConfigStore,
                    ) as T
                }
            }
    }
}
