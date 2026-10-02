package com.tempcompany.tempapp.ui.screens.login

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.R
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.auth.AuthManager
import com.tempcompany.tempapp.push.DeviceRegistrar
import com.tempcompany.tempapp.ui.nav.Routes
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * EXEMPLAR SCREEN — the pattern every screen in this app follows:
 * ViewModel exposes an immutable UiState StateFlow; all network via repositories
 * returning ApiResult; errors surfaced verbatim from the server (the API's
 * messages are user-appropriate, e.g. the suspended-company 403), never a stack
 * trace; Loading is explicit UI state.
 */

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val loading: Boolean = false,
    val error: String? = null,
    /** Suspended/cancelled companies get a distinct, prominent presentation (403). */
    val isAccountBlocked: Boolean = false,
)

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val authManager: AuthManager,
    private val deviceRegistrar: DeviceRegistrar,
) : ViewModel() {

    private val _state = MutableStateFlow(LoginUiState())
    val state: StateFlow<LoginUiState> = _state.asStateFlow()

    fun onEmail(value: String) = _state.value.let { _state.value = it.copy(email = value, error = null, isAccountBlocked = false) }
    fun onPassword(value: String) = _state.value.let { _state.value = it.copy(password = value, error = null, isAccountBlocked = false) }

    fun submit(onSuccess: () -> Unit) {
        val s = _state.value
        if (s.loading) return
        if (s.email.isBlank() || s.password.isBlank()) {
            _state.value = s.copy(error = "Enter your email and password.")
            return
        }
        _state.value = s.copy(loading = true, error = null, isAccountBlocked = false)
        viewModelScope.launch {
            when (val result = authManager.login(s.email.trim(), s.password)) {
                is ApiResult.Success -> {
                    deviceRegistrar.registerIfPossible() // FCM no-op until configured
                    onSuccess()
                }
                is ApiResult.Error -> _state.value = _state.value.copy(
                    loading = false,
                    error = result.message,
                    // 403 = the API's suspended/cancelled-company rejection — show it
                    // as an account problem, not a credentials problem.
                    isAccountBlocked = result.httpCode == 403,
                )
            }
        }
    }
}

@Composable
fun LoginScreen(
    navController: NavController,
    viewModel: LoginViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .imePadding()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Column(Modifier.widthIn(max = 420.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
                stringResource(R.string.app_name),
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.primary,
            )
            Text(
                "Sign in to your company dashboard",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = 4.dp, bottom = 28.dp),
            )

            OutlinedTextField(
                value = state.email,
                onValueChange = viewModel::onEmail,
                label = { Text("Email") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = state.password,
                onValueChange = viewModel::onPassword,
                label = { Text("Password") },
                singleLine = true,
                visualTransformation = PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
            )

            if (state.error != null) {
                Text(
                    text = if (state.isAccountBlocked) "Account unavailable: ${state.error}" else state.error!!,
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(top = 12.dp),
                )
                if (state.isAccountBlocked) {
                    Text(
                        "Contact support or your account owner to restore access.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 4.dp),
                    )
                }
            }

            Button(
                onClick = {
                    viewModel.submit {
                        navController.navigate(Routes.DASHBOARD) {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                },
                enabled = !state.loading,
                modifier = Modifier.fillMaxWidth().padding(top = 20.dp),
            ) {
                if (state.loading) {
                    CircularProgressIndicator(
                        modifier = Modifier.padding(end = 8.dp).widthIn(max = 18.dp),
                        strokeWidth = 2.dp,
                    )
                }
                Text(if (state.loading) "Signing in…" else "Sign in")
            }

            Text(
                "Password resets are handled on the web dashboard.",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = 16.dp),
            )
        }
    }
}
