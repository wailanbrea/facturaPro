package com.facturador.facturapro.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.repository.AuthRepositoryContract
import com.facturador.facturapro.data.repository.SettingsRepositoryContract
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class LoginViewModel(
    private val authRepository: AuthRepositoryContract,
    private val settingsRepository: SettingsRepositoryContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            authRepository.session.collectLatest { session ->
                _uiState.update {
                    it.copy(
                        isAuthenticated = session != null,
                        userName = session?.userName,
                        errorMessage = null,
                    )
                }

                if (session != null) {
                    loadBootstrap()
                }
            }
        }
    }

    fun onEmailChanged(value: String) {
        _uiState.update { it.copy(email = value, errorMessage = null) }
    }

    fun onPasswordChanged(value: String) {
        _uiState.update { it.copy(password = value, errorMessage = null) }
    }

    fun login() {
        val state = _uiState.value
        if (!state.canSubmit) {
            _uiState.update { it.copy(errorMessage = "Correo y password son obligatorios.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }
            val result = authRepository.login(state.email, state.password)

            result.fold(
                onSuccess = {
                    _uiState.update { current ->
                        current.copy(
                            password = "",
                            isLoading = false,
                            errorMessage = null,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "No se pudo iniciar sesion.",
                        )
                    }
                },
            )
        }
    }

    fun retryBootstrap() {
        loadBootstrap()
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
            _uiState.update {
                LoginUiState(email = it.email)
            }
        }
    }

    private fun loadBootstrap() {
        viewModelScope.launch {
            _uiState.update { it.copy(isBootstrapLoading = true, errorMessage = null) }
            settingsRepository.loadBootstrap().fold(
                onSuccess = { bootstrap ->
                    _uiState.update {
                        it.copy(
                            bootstrap = bootstrap,
                            isBootstrapLoading = false,
                            errorMessage = null,
                        )
                    }
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isBootstrapLoading = false,
                            errorMessage = error.message ?: "No se pudo cargar la configuracion.",
                        )
                    }
                },
            )
        }
    }

    companion object {
        fun factory(
            authRepository: AuthRepositoryContract,
            settingsRepository: SettingsRepositoryContract,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(LoginViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return LoginViewModel(authRepository, settingsRepository) as T
            }
        }
    }
}
