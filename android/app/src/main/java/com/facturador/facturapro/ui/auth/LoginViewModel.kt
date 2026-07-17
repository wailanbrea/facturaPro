package com.facturador.facturapro.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.local.ServerConfigStoreContract
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
    private val serverConfigStore: ServerConfigStoreContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            serverConfigStore.apiBaseUrl.collectLatest { apiBaseUrl ->
                _uiState.update {
                    it.copy(
                        currentApiBaseUrl = apiBaseUrl,
                        serverUrlInput = if (it.serverUrlInput.isBlank()) apiBaseUrl else it.serverUrlInput,
                    )
                }
            }
        }

        viewModelScope.launch {
            authRepository.session.collectLatest { session ->
                _uiState.update {
                    it.copy(
                        isAuthenticated = session != null,
                        isSessionLoaded = true,
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

    fun onServerUrlChanged(value: String) {
        _uiState.update {
            it.copy(serverUrlInput = value, serverMessage = null, errorMessage = null)
        }
    }

    fun saveServerUrl() {
        viewModelScope.launch {
            val rawValue = _uiState.value.serverUrlInput
            _uiState.update { it.copy(isSavingServerUrl = true, serverMessage = null, errorMessage = null) }

            serverConfigStore.saveApiBaseUrl(rawValue).fold(
                onSuccess = { normalized ->
                    authRepository.logout()
                    _uiState.update {
                        LoginUiState(
                            email = it.email,
                            currentApiBaseUrl = normalized,
                            serverUrlInput = normalized,
                            serverMessage = "Servidor guardado. Intenta iniciar sesion nuevamente.",
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
            authRepository.logout()
            _uiState.update {
                LoginUiState(
                    email = it.email,
                    currentApiBaseUrl = defaultUrl,
                    serverUrlInput = defaultUrl,
                    serverMessage = "Servidor restaurado al predeterminado.",
                )
            }
        }
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
                    val message = error.message ?: "No se pudo cargar la configuracion."
                    if (message.contains("Sesion expirada", ignoreCase = true)) {
                        authRepository.logout()
                    }

                    _uiState.update {
                        it.copy(
                            isBootstrapLoading = false,
                            isAuthenticated = !message.contains("Sesion expirada", ignoreCase = true),
                            errorMessage = message,
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
            serverConfigStore: ServerConfigStoreContract,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                require(modelClass.isAssignableFrom(LoginViewModel::class.java)) {
                    "Unknown ViewModel class: ${modelClass.name}"
                }

                return LoginViewModel(
                    authRepository = authRepository,
                    settingsRepository = settingsRepository,
                    serverConfigStore = serverConfigStore,
                ) as T
            }
        }
    }
}
