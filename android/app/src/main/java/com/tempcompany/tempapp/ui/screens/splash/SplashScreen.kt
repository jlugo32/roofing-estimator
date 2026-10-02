package com.tempcompany.tempapp.ui.screens.splash

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.auth.AuthManager
import com.tempcompany.tempapp.data.repo.CompanyRepository
import com.tempcompany.tempapp.ui.nav.Routes
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject

@HiltViewModel
class SplashViewModel @Inject constructor(
    val authManager: AuthManager,
    val companyRepository: CompanyRepository,
) : ViewModel()

/**
 * Auth check per spec: a stored token is validated with a lightweight
 * company/profile.php call, then we route. A network failure with a stored
 * token still opens the dashboard (screens each handle their own offline
 * state) — only a definitive 401 sends the user to Login.
 */
@Composable
fun SplashScreen(
    navController: NavController,
    viewModel: SplashViewModel = hiltViewModel(),
) {
    LaunchedEffect(Unit) {
        val destination = if (!viewModel.authManager.isLoggedIn()) {
            Routes.LOGIN
        } else {
            when (val r = viewModel.companyRepository.profile()) {
                is ApiResult.Success -> Routes.DASHBOARD
                is ApiResult.Error ->
                    if (r.isNetwork) Routes.DASHBOARD // offline ≠ logged out
                    else Routes.LOGIN                 // 401 already cleared the token
            }
        }
        navController.navigate(destination) {
            popUpTo(Routes.SPLASH) { inclusive = true }
        }
    }

    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator()
    }
}
