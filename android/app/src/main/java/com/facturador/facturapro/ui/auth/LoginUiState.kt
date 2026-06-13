package com.facturador.facturapro.ui.auth

import com.facturador.facturapro.domain.model.BootstrapCatalogs

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val isLoading: Boolean = false,
    val isBootstrapLoading: Boolean = false,
    val isAuthenticated: Boolean = false,
    val userName: String? = null,
    val bootstrap: BootstrapCatalogs? = null,
    val errorMessage: String? = null,
) {
    val canSubmit: Boolean
        get() = email.isNotBlank() && password.isNotBlank() && !isLoading
}
