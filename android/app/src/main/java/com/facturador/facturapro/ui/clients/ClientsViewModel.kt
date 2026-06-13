package com.facturador.facturapro.ui.clients

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.ClientRepository
import com.facturador.facturapro.domain.model.ClientDraft
import com.facturador.facturapro.domain.model.ClientRecord
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class ClientsViewModel(
    private val repository: ClientRepository,
) : ViewModel() {
    private val _uiState = MutableStateFlow(ClientsUiState())
    val uiState: StateFlow<ClientsUiState> = _uiState.asStateFlow()

    init {
        refresh()
    }

    fun onSearchChanged(value: String) {
        _uiState.update { it.copy(searchQuery = value, errorMessage = null) }
    }

    fun refresh() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            repository.list(_uiState.value.searchQuery).fold(
                onSuccess = { clients ->
                    _uiState.update {
                        it.copy(
                            clients = clients,
                            isLoading = false,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar clientes.",
                        )
                    }
                },
            )
        }
    }

    fun startEdit(client: ClientRecord) {
        _uiState.update { it.copy(editingClient = client, errorMessage = null) }
    }

    fun endEdit() {
        _uiState.update { it.copy(editingClient = null, errorMessage = null) }
    }

    fun createClient(draft: ClientDraft) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null, savedClientId = null) }
            repository.create(draft).fold(
                onSuccess = { client ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            savedClientId = client.id,
                            clients = (it.clients + client).sortedBy { record -> record.name.lowercase() },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo crear el cliente.",
                        )
                    }
                },
            )
        }
    }

    fun updateClient(id: Long, draft: ClientDraft) {
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null, savedClientId = null) }
            repository.update(id, draft).fold(
                onSuccess = { client ->
                    _uiState.update { current ->
                        current.copy(
                            isSaving = false,
                            savedClientId = client.id,
                            editingClient = null,
                            clients = current.clients
                                .map { existing -> if (existing.id == client.id) client else existing }
                                .sortedBy { record -> record.name.lowercase() },
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            errorMessage = error.message ?: "No se pudo actualizar el cliente.",
                        )
                    }
                },
            )
        }
    }

    fun consumeSaveEvent() {
        _uiState.update { it.copy(savedClientId = null) }
    }

    companion object {
        fun factory(repository: ClientRepository): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(ClientsViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return ClientsViewModel(repository) as T
            }
        }
    }
}
