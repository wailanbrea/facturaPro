package com.facturador.facturapro.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.local.ServerConfigStoreContract
import com.facturador.facturapro.data.repository.AuthRepositoryContract
import com.facturador.facturapro.data.repository.SettingsRepositoryContract
import com.facturador.facturapro.domain.model.AuthSession
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
    private var isLoggingOut = false

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
                authRepository.savedEmail,
            ) { session, rememberSession, biometricEnabled, savedEmail ->
                SessionFlowData(session, rememberSession, biometricEnabled, savedEmail)
            }.collectLatest { data ->
                if (isLoggingOut) return@collectLatest
                val requiresBiometric = data.session != null &&
                    (data.biometricEnabled || inactivityLockRequested) &&
                    !sessionUnlocked
                val authenticated = data.session != null && !requiresBiometric
                _uiState.update {
                    it.copy(
                        isAuthenticated = authenticated,
                        isSessionLoaded = true,
                        rememberSession = data.rememberSession,
                        biometricEnabled = data.biometricEnabled,
                        requiresBiometricUnlock = requiresBiometric,
                        hasSavedSession = data.session != null,
                        email = if (it.email.isBlank()) {
                            data.session?.userEmail.orEmpty().ifBlank { data.savedEmail }
                        } else it.email,
                        userName = data.session?.userName,
                        permissions = data.session?.permissions.orEmpty(),
                        bootstrap = if (data.session == null) null else it.bootstrap,
                        isBootstrapLoading = if (data.session == null) false else it.isBootstrapLoading,
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
        _uiState.update {
            it.copy(
                biometricEnabled = value,
                rememberSession = if (value) true else it.rememberSession,
            )
        }
    }

    fun unlockWithBiometrics() {
        if (isLoggingOut) return
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

    fun lockSession() {
        val state = _uiState.value
        if (!state.isAuthenticated || !state.hasSavedSession) return

        sessionUnlocked = false
        inactivityLockRequested = false
        _uiState.update {
            it.copy(
                isAuthenticated = false,
                requiresBiometricUnlock = it.biometricEnabled,
                password = "",
                bootstrap = null,
                errorMessage = null,
            )
        }
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
        if (_uiState.value.biometricEnabled && _uiState.value.hasSavedSession) {
            sessionUnlocked = false
            inactivityLockRequested = false
            _uiState.update {
                it.copy(
                    password = "",
                    isAuthenticated = false,
                    isSessionLoaded = true,
                    isLoading = false,
                    isBootstrapLoading = false,
                    requiresBiometricUnlock = false,
                    errorMessage = null,
                    serverMessage = null,
                )
            }
            return
        }

        viewModelScope.launch {
            isLoggingOut = true
            sessionUnlocked = false
            inactivityLockRequested = false
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
            try {
                authRepository.logout()
            } finally {
                isLoggingOut = false
            }
        }
    }

    fun unlinkAccount() {
        viewModelScope.launch {
            isLoggingOut = true
            sessionUnlocked = false
            inactivityLockRequested = false
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
                    biometricEnabled = false,
                    errorMessage = null,
                    serverMessage = null,
                )
            }
            try {
                authRepository.logout()
            } finally {
                isLoggingOut = false
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

private data class SessionFlowData(
    val session: AuthSession?,
    val rememberSession: Boolean,
    val biometricEnabled: Boolean,
    val savedEmail: String,
)
