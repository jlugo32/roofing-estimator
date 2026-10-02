package com.tempcompany.tempapp.ui.nav

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Business
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.RequestQuote
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationRail
import androidx.compose.material3.NavigationRailItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.windowsizeclass.WindowSizeClass
import androidx.compose.material3.windowsizeclass.WindowWidthSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import androidx.navigation.NavType
import com.tempcompany.tempapp.data.auth.AuthManager
import com.tempcompany.tempapp.ui.screens.account.AccountScreen
import com.tempcompany.tempapp.ui.screens.clients.ClientDetailScreen
import com.tempcompany.tempapp.ui.screens.clients.ClientsScreen
import com.tempcompany.tempapp.ui.screens.company.CompanyScreen
import com.tempcompany.tempapp.ui.screens.dashboard.DashboardScreen
import com.tempcompany.tempapp.ui.screens.estimates.CreateEstimateScreen
import com.tempcompany.tempapp.ui.screens.estimates.EstimatesScreen
import com.tempcompany.tempapp.ui.screens.login.LoginScreen
import com.tempcompany.tempapp.ui.screens.splash.SplashScreen
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject

/** Route constants — the single navigation vocabulary. */
object Routes {
    const val SPLASH = "splash"
    const val LOGIN = "login"
    const val DASHBOARD = "dashboard"
    const val CLIENTS = "clients"
    const val CLIENT_DETAIL = "clients/{clientId}"
    const val ESTIMATES = "estimates"
    const val CREATE_ESTIMATE = "estimates/create?clientId={clientId}"
    const val COMPANY = "company"
    const val ACCOUNT = "account"

    fun clientDetail(id: Long) = "clients/$id"
    fun createEstimate(clientId: Long? = null) =
        if (clientId != null) "estimates/create?clientId=$clientId" else "estimates/create?clientId=-1"
}

@HiltViewModel
class RootViewModel @Inject constructor(val authManager: AuthManager) : ViewModel()

private data class TopDest(val route: String, val label: String, val icon: ImageVector)

private val topDestinations = listOf(
    TopDest(Routes.DASHBOARD, "Home", Icons.Filled.Home),
    TopDest(Routes.CLIENTS, "Clients", Icons.AutoMirrored.Filled.List),
    TopDest(Routes.ESTIMATES, "Estimates", Icons.Filled.RequestQuote),
    TopDest(Routes.COMPANY, "Company", Icons.Filled.Business),
    TopDest(Routes.ACCOUNT, "Account", Icons.Filled.Person),
)

/**
 * Root of the UI: nav graph + adaptive chrome (bottom bar on phones, nav rail on
 * tablets) + the global 401 hook. Any AuthManager LoggedOut event lands here and
 * routes to Login immediately — spec: never silently retry.
 */
@Composable
fun AppRoot(
    windowSizeClass: WindowSizeClass,
    viewModel: RootViewModel = hiltViewModel(),
) {
    val navController = rememberNavController()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(Unit) {
        viewModel.authManager.events.collect { event ->
            when (event) {
                is AuthManager.AuthEvent.LoggedOut -> {
                    navController.navigate(Routes.LOGIN) {
                        popUpTo(0) { inclusive = true }
                    }
                    event.reason?.let { snackbarHostState.showSnackbar(it) }
                }
            }
        }
    }

    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route
    val showChrome = topDestinations.any { it.route == currentRoute }
    val useRail = windowSizeClass.widthSizeClass != WindowWidthSizeClass.Compact

    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        bottomBar = {
            if (showChrome && !useRail) {
                NavigationBar {
                    topDestinations.forEach { dest ->
                        NavigationBarItem(
                            selected = currentRoute == dest.route,
                            onClick = { navController.navigateTop(dest.route) },
                            icon = { Icon(dest.icon, contentDescription = dest.label) },
                            label = { Text(dest.label) },
                        )
                    }
                }
            }
        },
    ) { padding ->
        Row(Modifier.fillMaxSize().padding(padding)) {
            if (showChrome && useRail) {
                NavigationRail {
                    topDestinations.forEach { dest ->
                        NavigationRailItem(
                            selected = currentRoute == dest.route,
                            onClick = { navController.navigateTop(dest.route) },
                            icon = { Icon(dest.icon, contentDescription = dest.label) },
                            label = { Text(dest.label) },
                        )
                    }
                }
            }

            NavHost(
                navController = navController,
                startDestination = Routes.SPLASH,
                modifier = Modifier.weight(1f),
            ) {
                composable(Routes.SPLASH) { SplashScreen(navController) }
                composable(Routes.LOGIN) { LoginScreen(navController) }
                composable(Routes.DASHBOARD) { DashboardScreen(navController, windowSizeClass) }
                composable(Routes.CLIENTS) { ClientsScreen(navController, windowSizeClass) }
                composable(
                    Routes.CLIENT_DETAIL,
                    arguments = listOf(navArgument("clientId") { type = NavType.LongType }),
                ) { entry ->
                    ClientDetailScreen(navController, entry.arguments?.getLong("clientId") ?: -1L)
                }
                composable(Routes.ESTIMATES) { EstimatesScreen(navController, windowSizeClass) }
                composable(
                    Routes.CREATE_ESTIMATE,
                    arguments = listOf(navArgument("clientId") { type = NavType.LongType; defaultValue = -1L }),
                ) { entry ->
                    CreateEstimateScreen(
                        navController,
                        preselectedClientId = entry.arguments?.getLong("clientId").takeIf { it != null && it > 0 },
                    )
                }
                composable(Routes.COMPANY) { CompanyScreen(navController) }
                composable(Routes.ACCOUNT) { AccountScreen(navController) }
            }
        }
    }
}

/** Top-level navigation: single instance per destination, state preserved. */
private fun NavHostController.navigateTop(route: String) {
    navigate(route) {
        popUpTo(graph.findStartDestination().id) { saveState = true }
        launchSingleTop = true
        restoreState = true
    }
}
