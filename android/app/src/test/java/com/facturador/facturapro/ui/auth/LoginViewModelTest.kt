package com.facturador.facturapro.ui.auth

import com.facturador.facturapro.data.local.ServerConfigStoreContract
import com.facturador.facturapro.data.repository.AuthRepositoryContract
import com.facturador.facturapro.data.repository.SettingsRepositoryContract
import com.facturador.facturapro.domain.model.AuthSession
import com.facturador.facturapro.domain.model.BankAccountCatalogItem
import com.facturador.facturapro.domain.model.BootstrapCatalogs
import com.facturador.facturapro.domain.model.CurrencyCatalogItem
import com.facturador.facturapro.domain.model.FiscalProfileCatalogItem
import com.facturador.facturapro.domain.model.LegalTextCatalogItem
import com.facturador.facturapro.domain.model.PaymentTermCatalogItem
import com.facturador.facturapro.domain.model.TaxCatalogItem
import com.facturador.facturapro.domain.model.WarrantyCatalogItem
import com.facturador.facturapro.testutil.MainDispatcherRule
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class LoginViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun login_authenticates_and_loads_bootstrap() = runTest {
        val authRepository = FakeAuthRepository()
        val settingsRepository = FakeSettingsRepository(Result.success(sampleBootstrap()))
        val viewModel = LoginViewModel(authRepository, settingsRepository, FakeServerConfigStore())

        viewModel.onEmailChanged("admin@facturapro.local")
        viewModel.onPasswordChanged("FacturaPro123!")
        viewModel.login()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertTrue(state.isAuthenticated)
        assertEquals("Admin FacturaPro", state.userName)
        assertNotNull(state.bootstrap)
        assertEquals(1, settingsRepository.loadCalls)
        assertEquals("", state.password)
        assertNull(state.errorMessage)
        assertFalse(state.isLoading)
        assertFalse(state.isBootstrapLoading)
    }

    @Test
    fun login_rejects_empty_credentials_without_hitting_repository() = runTest {
        val authRepository = FakeAuthRepository()
        val viewModel = LoginViewModel(
            authRepository = authRepository,
            settingsRepository = FakeSettingsRepository(Result.success(sampleBootstrap())),
            serverConfigStore = FakeServerConfigStore(),
        )

        advanceUntilIdle()
        viewModel.login()
        advanceUntilIdle()

        assertEquals("Correo y password son obligatorios.", viewModel.uiState.value.errorMessage)
        assertEquals(0, authRepository.loginCalls)
    }

    @Test
    fun logout_returns_to_login_without_resetting_session_loading_state() = runTest {
        val authRepository = FakeAuthRepository()
        val viewModel = LoginViewModel(
            authRepository = authRepository,
            settingsRepository = FakeSettingsRepository(Result.success(sampleBootstrap())),
            serverConfigStore = FakeServerConfigStore(),
        )

        viewModel.onEmailChanged("facturador@facturapro.com")
        viewModel.onPasswordChanged("facturador1234")
        viewModel.login()
        advanceUntilIdle()

        viewModel.logout()
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertTrue(viewModel.uiState.value.isSessionLoaded)
        assertFalse(viewModel.uiState.value.isBootstrapLoading)
    }

    @Test
    fun remembered_biometric_session_waits_for_fingerprint_before_opening_workspace() = runTest {
        val authRepository = FakeAuthRepository(
            initialSession = testSession(),
            initialBiometricEnabled = true,
        )
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertTrue(viewModel.uiState.value.requiresBiometricUnlock)

        viewModel.unlockWithBiometrics()
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.isAuthenticated)
        assertFalse(viewModel.uiState.value.requiresBiometricUnlock)
    }

    @Test
    fun inactivity_locks_an_open_session_without_deleting_it() = runTest {
        val authRepository = FakeAuthRepository()
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        viewModel.onEmailChanged("admin@facturapro.local")
        viewModel.onPasswordChanged("FacturaPro123!")
        viewModel.login()
        advanceUntilIdle()

        viewModel.lockAfterInactivity()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertTrue(viewModel.uiState.value.hasSavedSession)
        assertTrue(viewModel.uiState.value.requiresBiometricUnlock)
    }

    @Test
    fun logout_when_biometric_enabled_keeps_session_saved_for_fingerprint_unlock() = runTest {
        val authRepository = FakeAuthRepository(
            initialSession = testSession(),
            initialBiometricEnabled = true,
        )
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.requiresBiometricUnlock)

        viewModel.logout()
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertFalse(viewModel.uiState.value.requiresBiometricUnlock)
        assertTrue(viewModel.uiState.value.hasSavedSession)
        assertTrue(viewModel.uiState.value.biometricEnabled)

        // User can tap "Iniciar sesión con huella" to unlock
        viewModel.unlockWithBiometrics()
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.isAuthenticated)
    }

    @Test
    fun unlinkAccount_clears_session_completely() = runTest {
        val authRepository = FakeAuthRepository(
            initialSession = testSession(),
            initialBiometricEnabled = true,
        )
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        advanceUntilIdle()

        viewModel.unlinkAccount()
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertFalse(viewModel.uiState.value.requiresBiometricUnlock)
        assertFalse(viewModel.uiState.value.hasSavedSession)
        assertFalse(viewModel.uiState.value.biometricEnabled)
    }

    @Test
    fun savedEmail_prefills_login_field_when_no_session_is_active() = runTest {
        val authRepository = FakeAuthRepository(
            initialSession = null,
            initialSavedEmail = "guardado@facturapro.com",
        )
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        advanceUntilIdle()

        assertEquals("guardado@facturapro.com", viewModel.uiState.value.email)
    }

    @Test
    fun lockSession_keeps_session_saved_and_requires_biometric_unlock() = runTest {
        val authRepository = FakeAuthRepository()
        val viewModel = LoginViewModel(
            authRepository,
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        viewModel.onEmailChanged("admin@facturapro.local")
        viewModel.onPasswordChanged("FacturaPro123!")
        viewModel.onBiometricEnabledChanged(true)
        viewModel.login()
        advanceUntilIdle()

        assertTrue(viewModel.uiState.value.isAuthenticated)

        viewModel.lockSession()
        advanceUntilIdle()

        assertFalse(viewModel.uiState.value.isAuthenticated)
        assertTrue(viewModel.uiState.value.hasSavedSession)
        assertTrue(viewModel.uiState.value.requiresBiometricUnlock)
    }

    @Test
    fun enabling_biometrics_automatically_enables_rememberSession() = runTest {
        val viewModel = LoginViewModel(
            FakeAuthRepository(),
            FakeSettingsRepository(Result.success(sampleBootstrap())),
            FakeServerConfigStore(),
        )
        viewModel.onRememberSessionChanged(false)
        assertFalse(viewModel.uiState.value.rememberSession)

        viewModel.onBiometricEnabledChanged(true)
        assertTrue(viewModel.uiState.value.biometricEnabled)
        assertTrue(viewModel.uiState.value.rememberSession)
    }
}

private class FakeServerConfigStore(
    initialUrl: String = DEFAULT_URL,
) : ServerConfigStoreContract {
    private val apiBaseUrlState = MutableStateFlow(initialUrl)

    override val apiBaseUrl: Flow<String> = apiBaseUrlState

    override suspend fun currentApiBaseUrl(): String = apiBaseUrlState.value

    override suspend fun saveApiBaseUrl(rawValue: String): Result<String> {
        apiBaseUrlState.value = rawValue
        return Result.success(rawValue)
    }

    override suspend fun resetApiBaseUrl(): String {
        apiBaseUrlState.value = DEFAULT_URL
        return DEFAULT_URL
    }

    private companion object {
        const val DEFAULT_URL = "https://facturacion.tutecnicoautorizado.com/api/"
    }
}

private class FakeAuthRepository(
    initialSession: AuthSession? = null,
    initialBiometricEnabled: Boolean = false,
    initialSavedEmail: String = "",
) : AuthRepositoryContract {
    private val sessionFlow = MutableStateFlow(initialSession)
    private val rememberSessionFlow = MutableStateFlow(true)
    private val biometricEnabledFlow = MutableStateFlow(initialBiometricEnabled)
    private val savedEmailFlow = MutableStateFlow(initialSavedEmail)

    var loginCalls: Int = 0
        private set

    override val session: Flow<AuthSession?> = sessionFlow
    override val rememberSession: Flow<Boolean> = rememberSessionFlow
    override val biometricEnabled: Flow<Boolean> = biometricEnabledFlow
    override val savedEmail: Flow<String> = savedEmailFlow

    override suspend fun login(
        email: String,
        password: String,
        rememberSession: Boolean,
        biometricEnabled: Boolean,
    ): Result<AuthSession> {
        loginCalls++
        rememberSessionFlow.value = rememberSession
        biometricEnabledFlow.value = biometricEnabled
        savedEmailFlow.value = email
        val session = testSession(email)
        sessionFlow.value = session
        return Result.success(session)
    }

    override suspend fun logout() {
        sessionFlow.value = null
        biometricEnabledFlow.value = false
    }
}

private fun testSession(email: String = "admin@facturapro.local") = AuthSession(
    tokenType = "Bearer",
    accessToken = "token",
    userId = 1L,
    userName = "Admin FacturaPro",
    userEmail = email,
)

private class FakeSettingsRepository(
    private val bootstrapResult: Result<BootstrapCatalogs>,
) : SettingsRepositoryContract {
    var loadCalls: Int = 0
        private set

    override suspend fun loadBootstrap(): Result<BootstrapCatalogs> {
        loadCalls++
        return bootstrapResult
    }
}

private fun sampleBootstrap(): BootstrapCatalogs = BootstrapCatalogs(
    currencies = listOf(CurrencyCatalogItem(id = 1, code = "DOP", name = "Peso Dominicano", symbol = "RD$", isDefault = true)),
    taxes = listOf(TaxCatalogItem(id = 1, name = "ITBIS 18%", rate = "18.0000", isDefault = true)),
    paymentTerms = listOf(PaymentTermCatalogItem(id = 1, name = "AL CONTADO", days = 0, isDefault = true)),
    warranties = listOf(WarrantyCatalogItem(id = 1, title = "Garantia base", durationMonths = 1, isDefault = true)),
    bankAccounts = listOf(BankAccountCatalogItem(id = 1, name = "Cuenta principal", accountHolder = "Titular prueba", accountType = "official", isDefault = true)),
    fiscalProfiles = listOf(
        FiscalProfileCatalogItem(
            id = 1,
            name = "Perfil fiscal",
            isDefault = true,
            logoPath = null,
            logos = emptyList(),
            nextInvoiceNumber = "FAC-PF-000001",
            nextQuotationNumber = "PRES-PF-000001",
        ),
    ),
    legalTexts = listOf(LegalTextCatalogItem(id = 1, name = "Texto base", legalFooter = "Pie", warrantyText = "Garantia", conformityText = "CONFORMIDAD DEL CLIENTE", isDefault = true)),
)
