package com.facturador.facturapro.ui.clients

import com.facturador.facturapro.domain.model.ClientRecord

data class ClientsUiState(
    val clients: List<ClientRecord> = emptyList(),
    val searchQuery: String = "",
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val errorMessage: String? = null,
    val savedClientId: Long? = null,
    val editingClient: ClientRecord? = null,
)
