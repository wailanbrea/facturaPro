package com.facturador.facturapro.ui.auth

import com.facturador.facturapro.domain.model.BootstrapCatalogs

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val currentApiBaseUrl: String = "",
    val serverUrlInput: String = "",
    val isSavingServerUrl: Boolean = false,
    val isLoading: Boolean = false,
    val isBootstrapLoading: Boolean = false,
    val isAuthenticated: Boolean = false,
    val isSessionLoaded: Boolean = false,
    val userName: String? = null,
    val bootstrap: BootstrapCatalogs? = null,
    val errorMessage: String? = null,
    val serverMessage: String? = null,
) {
    val canSubmit: Boolean
        get() = email.isNotBlank() && password.isNotBlank() && !isLoading && !isSavingServerUrl
}
