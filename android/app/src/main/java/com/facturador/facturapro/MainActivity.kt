package com.facturador.facturapro

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.fragment.app.FragmentActivity
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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

class MainActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val container = (application as FacturaProApplication).container

        setContent {
            FacturaProTheme {
                FacturaProApp(container = container)
            }
        }
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

    LaunchedEffect(state.isAuthenticated) {
        val route = if (state.isAuthenticated) Routes.Home else Routes.Login
        navController.navigate(route) {
            popUpTo(0)
            launchSingleTop = true
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
