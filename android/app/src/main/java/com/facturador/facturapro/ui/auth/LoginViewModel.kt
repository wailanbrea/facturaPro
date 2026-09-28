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
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class LoginViewModel(
    private val authRepository: AuthRepositoryContract,
    private val settingsRepository: SettingsRepositoryContract,
    private val serverConfigStore: ServerConfigStoreContract,
) : ViewModel() {
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()
    private var sessionUnlocked = false
    private var inactivityLockRequested = false

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
            combine(
                authRepository.session,
                authRepository.rememberSession,
                authRepository.biometricEnabled,
            ) { session, rememberSession, biometricEnabled ->
                Triple(session, rememberSession, biometricEnabled)
            }.collectLatest { (session, rememberSession, biometricEnabled) ->
                val requiresBiometric = session != null &&
                    (biometricEnabled || inactivityLockRequested) &&
                    !sessionUnlocked
                val authenticated = session != null && !requiresBiometric
                _uiState.update {
                    it.copy(
                        isAuthenticated = authenticated,
                        isSessionLoaded = true,
                        rememberSession = rememberSession,
                        biometricEnabled = biometricEnabled,
                        requiresBiometricUnlock = requiresBiometric,
                        hasSavedSession = session != null,
                        email = if (it.email.isBlank()) session?.userEmail.orEmpty() else it.email,
                        userName = session?.userName,
                        permissions = session?.permissions.orEmpty(),
                        bootstrap = if (session == null) null else it.bootstrap,
                        isBootstrapLoading = if (session == null) false else it.isBootstrapLoading,
                        errorMessage = null,
                    )
                }

                if (authenticated) {
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

    fun onRememberSessionChanged(value: Boolean) {
        _uiState.update {
            it.copy(
                rememberSession = value,
                biometricEnabled = if (value) it.biometricEnabled else false,
            )
        }
    }

    fun onBiometricEnabledChanged(value: Boolean) {
        _uiState.update { it.copy(biometricEnabled = value && it.rememberSession) }
    }

    fun unlockWithBiometrics() {
        val state = _uiState.value
        if (!state.hasSavedSession) return
        sessionUnlocked = true
        inactivityLockRequested = false
        _uiState.update {
            it.copy(
                isAuthenticated = true,
                requiresBiometricUnlock = false,
                errorMessage = null,
            )
        }
        loadBootstrap()
    }

    fun lockAfterInactivity() {
        inactivityLockRequested = true
        val state = _uiState.value
        if (!state.isAuthenticated || !state.hasSavedSession) return

        sessionUnlocked = false
        _uiState.update {
            it.copy(
                isAuthenticated = false,
                requiresBiometricUnlock = true,
                password = "",
                bootstrap = null,
                errorMessage = "La sesión se bloqueó después de 10 minutos de inactividad.",
            )
        }
    }

    fun onBiometricError(message: String) {
        _uiState.update { it.copy(errorMessage = message) }
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
                        it.copy(
                            password = "",
                            currentApiBaseUrl = normalized,
                            serverUrlInput = normalized,
                            isAuthenticated = false,
                            isSessionLoaded = true,
                            isLoading = false,
                            isBootstrapLoading = false,
                            userName = null,
                            permissions = emptySet(),
                            bootstrap = null,
                            errorMessage = null,
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
                it.copy(
                    password = "",
                    currentApiBaseUrl = defaultUrl,
                    serverUrlInput = defaultUrl,
                    isAuthenticated = false,
                    isSessionLoaded = true,
                    isLoading = false,
                    isBootstrapLoading = false,
                    userName = null,
                    permissions = emptySet(),
                    bootstrap = null,
                    errorMessage = null,
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
            sessionUnlocked = true
            inactivityLockRequested = false
            val result = authRepository.login(
                email = state.email,
                password = state.password,
                rememberSession = state.rememberSession,
                biometricEnabled = state.biometricEnabled,
            )

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
                    sessionUnlocked = false
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
            sessionUnlocked = false
            inactivityLockRequested = false
            authRepository.logout()
            _uiState.update {
                it.copy(
                    password = "",
                    isAuthenticated = false,
                    isSessionLoaded = true,
                    isLoading = false,
                    isBootstrapLoading = false,
                    userName = null,
                    permissions = emptySet(),
                    bootstrap = null,
                    requiresBiometricUnlock = false,
                    hasSavedSession = false,
                    errorMessage = null,
                    serverMessage = null,
                )
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
                            permissions = bootstrap.userPermissions,
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
