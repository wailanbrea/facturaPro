package com.facturador.facturapro

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.fragment.app.FragmentActivity
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.facturador.facturapro.di.AppContainer
import com.facturador.facturapro.ui.auth.LoginScreen
import com.facturador.facturapro.ui.auth.LoginViewModel
import com.facturador.facturapro.ui.theme.FacturaProTheme
import com.facturador.facturapro.ui.workspace.WorkspaceScreen
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.material3.CircularProgressIndicator

class MainActivity : FragmentActivity() {
    private val inactivityHandler = Handler(Looper.getMainLooper())
    private var lastInteractionAt = 0L
    private var pendingInactivityLock = false
    private var inactivityListener: (() -> Unit)? = null
    private val inactivityRunnable = Runnable { inactivityListener?.invoke() }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        lastInteractionAt = getPreferences(MODE_PRIVATE).getLong(
            LAST_INTERACTION_KEY,
            System.currentTimeMillis(),
        )
        enableEdgeToEdge()

        setContent {
            FacturaProTheme {
                val container = (application as FacturaProApplication).container
                FacturaProApp(container = container)
            }
        }
    }

    override fun onUserInteraction() {
        super.onUserInteraction()
        markUserActive()
    }

    override fun onResume() {
        super.onResume()
        val inactiveFor = System.currentTimeMillis() - lastInteractionAt
        if (inactiveFor >= INACTIVITY_TIMEOUT_MS) {
            pendingInactivityLock = true
            inactivityListener?.invoke()
        }
        scheduleInactivityLock()
    }

    override fun onPause() {
        getPreferences(MODE_PRIVATE).edit()
            .putLong(LAST_INTERACTION_KEY, lastInteractionAt)
            .apply()
        super.onPause()
    }

    override fun onDestroy() {
        inactivityHandler.removeCallbacks(inactivityRunnable)
        inactivityListener = null
        super.onDestroy()
    }

    fun observeInactivity(listener: (() -> Unit)?) {
        inactivityListener = listener
        if (listener != null) {
            if (pendingInactivityLock) {
                pendingInactivityLock = false
                listener.invoke()
            }
            scheduleInactivityLock()
        } else {
            inactivityHandler.removeCallbacks(inactivityRunnable)
        }
    }

    fun markUserActive() {
        lastInteractionAt = System.currentTimeMillis()
        scheduleInactivityLock()
    }

    private fun scheduleInactivityLock() {
        inactivityHandler.removeCallbacks(inactivityRunnable)
        inactivityHandler.postDelayed(inactivityRunnable, INACTIVITY_TIMEOUT_MS)
    }

    private companion object {
        const val INACTIVITY_TIMEOUT_MS = 10 * 60 * 1_000L
        const val LAST_INTERACTION_KEY = "last_interaction_at"
    }
}

@Composable
fun FacturaProApp(container: AppContainer) {
    val navController = rememberNavController()
    val viewModel: LoginViewModel = viewModel(
        factory = LoginViewModel.factory(
            authRepository = container.authRepository,
            settingsRepository = container.settingsRepository,
            serverConfigStore = container.serverConfigStore,
        ),
    )
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val activity = androidx.compose.ui.platform.LocalContext.current as FragmentActivity
    val mainActivity = activity as MainActivity
    val biometricAuthenticators = BiometricManager.Authenticators.BIOMETRIC_STRONG or
        BiometricManager.Authenticators.BIOMETRIC_WEAK
    val biometricAvailable = remember(activity) {
        BiometricManager.from(activity).canAuthenticate(biometricAuthenticators) ==
            BiometricManager.BIOMETRIC_SUCCESS
    }
    val biometricPrompt = remember(activity, viewModel) {
        BiometricPrompt(
            activity,
            ContextCompat.getMainExecutor(activity),
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    viewModel.unlockWithBiometrics()
                    mainActivity.markUserActive()
                }

                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    if (errorCode != BiometricPrompt.ERROR_NEGATIVE_BUTTON &&
                        errorCode != BiometricPrompt.ERROR_USER_CANCELED &&
                        errorCode != BiometricPrompt.ERROR_CANCELED
                    ) {
                        viewModel.onBiometricError(errString.toString())
                    }
                }

                override fun onAuthenticationFailed() {
                    viewModel.onBiometricError("No se reconoció la huella. Inténtalo nuevamente.")
                }
            },
        )
    }
    val requestBiometricUnlock = {
        biometricPrompt.authenticate(
            BiometricPrompt.PromptInfo.Builder()
                .setTitle("Entrar a FacturaPro")
                .setSubtitle("Confirma tu huella para abrir la sesión guardada")
                .setNegativeButtonText("Usar contraseña")
                .setAllowedAuthenticators(biometricAuthenticators)
                .build(),
        )
    }


    androidx.compose.runtime.DisposableEffect(mainActivity, viewModel) {
        mainActivity.observeInactivity(viewModel::lockAfterInactivity)
        onDispose { mainActivity.observeInactivity(null) }
    }

    if (!state.isSessionLoaded) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            CircularProgressIndicator()
        }
        return
    }

    LaunchedEffect(state.isAuthenticated) {
        val currentRoute = navController.currentDestination?.route
        if (state.isAuthenticated) {
            if (currentRoute != Routes.Home) {
                navController.navigate(Routes.Home) {
                    popUpTo(0) { inclusive = true }
                    launchSingleTop = true
                }
            }
        } else {
            if (currentRoute != Routes.Login) {
                navController.navigate(Routes.Login) {
                    popUpTo(0) { inclusive = true }
                    launchSingleTop = true
                }
            }
        }
    }

    LaunchedEffect(state.requiresBiometricUnlock, biometricAvailable) {
        if (state.requiresBiometricUnlock && biometricAvailable) {
            requestBiometricUnlock()
        }
    }

    NavHost(
        navController = navController,
        startDestination = if (state.isAuthenticated) Routes.Home else Routes.Login,
    ) {
        composable(Routes.Login) {
            LoginScreen(
                state = state,
                onEmailChanged = viewModel::onEmailChanged,
                onPasswordChanged = viewModel::onPasswordChanged,
                onServerUrlChanged = viewModel::onServerUrlChanged,
                onSaveServerUrl = viewModel::saveServerUrl,
                onResetServerUrl = viewModel::resetServerUrl,
                onRememberSessionChanged = viewModel::onRememberSessionChanged,
                onBiometricEnabledChanged = viewModel::onBiometricEnabledChanged,
                biometricAvailable = biometricAvailable,
                onBiometricLogin = requestBiometricUnlock,
                onLogin = viewModel::login,
            )
        }
        composable(Routes.Home) {
            WorkspaceScreen(
                loginState = state,
                container = container,
                onRetryBootstrap = viewModel::retryBootstrap,
                onLogout = viewModel::logout,
            )
        }
    }
}

private object Routes {
    const val Login = "login"
    const val Home = "home"
}
