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
        const val DEFAULT_URL = "https://facturapro.bsolutions.dev/api/"
    }
}

private class FakeAuthRepository : AuthRepositoryContract {
    private val sessionFlow = MutableStateFlow<AuthSession?>(null)

    var loginCalls: Int = 0
        private set

    override val session: Flow<AuthSession?> = sessionFlow

    override suspend fun login(email: String, password: String): Result<AuthSession> {
        loginCalls++
        val session = AuthSession(
            tokenType = "Bearer",
            accessToken = "token",
            userId = 1L,
            userName = "Admin FacturaPro",
            userEmail = email,
        )
        sessionFlow.value = session
        return Result.success(session)
    }

    override suspend fun logout() {
        sessionFlow.value = null
    }
}

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
    bankAccounts = listOf(BankAccountCatalogItem(id = 1, name = "Cuenta principal", accountType = "official", isDefault = true)),
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
