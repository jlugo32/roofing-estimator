package com.tempcompany.tempapp.data.auth

import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.Envelope
import com.tempcompany.tempapp.data.api.LoginData
import com.tempcompany.tempapp.data.api.LoginRequest
import com.tempcompany.tempapp.data.api.safeApiCall
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Named
import javax.inject.Singleton

/**
 * Owns the session lifecycle. Uses the BARE ApiService (no auth interceptor) for
 * login/refresh so token maintenance can never recurse through itself.
 *
 * Auth rules from the spec:
 * - Proactive refresh when expiry is within [REFRESH_WINDOW_MS] — not 401-driven.
 * - On any 401: clear the token and emit [AuthEvent.LoggedOut]; the nav layer
 *   routes to Login immediately. No silent retries, no loops.
 * - Logout "revokes" the token using the frozen contract: auth/refresh.php
 *   rotates the presented token (it dies server-side) and we discard the
 *   replacement — net effect, no live token remains anywhere.
 */
@Singleton
class AuthManager @Inject constructor(
    @Named("bare") private val bareApi: ApiService,
    private val tokenStore: TokenStore,
) {

    sealed interface AuthEvent {
        /** Session ended (401, expiry, or explicit logout) — route to Login. */
        data class LoggedOut(val reason: String?) : AuthEvent
    }

    private val _events = MutableSharedFlow<AuthEvent>(extraBufferCapacity = 4)
    val events: SharedFlow<AuthEvent> = _events.asSharedFlow()

    private val _session = MutableStateFlow(tokenStore.session())
    val session: StateFlow<TokenStore.Session?> = _session.asStateFlow()

    private val refreshMutex = Mutex()

    fun isLoggedIn(): Boolean = tokenStore.session() != null

    suspend fun login(email: String, password: String): ApiResult<LoginData> {
        val result = safeApiCall { bareApi.login(LoginRequest(email, password)) }
        if (result is ApiResult.Success) {
            tokenStore.save(
                token = result.data.token,
                expiresAt = result.data.expiresAt,
                userName = result.data.user.name,
                userEmail = result.data.user.email,
                userRole = result.data.user.role,
                companyName = result.data.company.name,
                companySlug = result.data.company.slug,
            )
            _session.value = tokenStore.session()
        }
        return result
    }

    /** Current bearer value for the interceptor, or null when logged out. */
    fun currentToken(): String? = tokenStore.session()?.token

    /**
     * Refresh proactively when expiry is inside the window. Mutex-serialized so
     * parallel requests trigger at most one refresh; the token is re-checked
     * inside the lock. A refresh rejection means the token is already dead —
     * treat it as a logout, never loop.
     */
    suspend fun ensureFreshToken() {
        val s = tokenStore.session() ?: return
        if (System.currentTimeMillis() < s.expiresAtEpochMs - REFRESH_WINDOW_MS) return

        refreshMutex.withLock {
            val current = tokenStore.session() ?: return
            if (System.currentTimeMillis() < current.expiresAtEpochMs - REFRESH_WINDOW_MS) return

            when (val r = safeApiCall { bareApi.refresh("Bearer ${current.token}") }) {
                is ApiResult.Success -> {
                    tokenStore.save(token = r.data.token, expiresAt = r.data.expiresAt)
                    _session.value = tokenStore.session()
                }
                is ApiResult.Error -> if (!r.isNetwork) {
                    forceLogout("Your session expired. Please log in again.")
                } // network blips: keep the old token, the request itself will surface the error
            }
        }
    }

    /** Central 401 policy: clear stored token, route to Login. Called by repositories. */
    suspend fun onUnauthorized() {
        forceLogout("Your session expired. Please log in again.")
    }

    /**
     * Wraps an authed call with the 401 policy so no screen can forget it.
     */
    suspend fun <T> authedCall(block: suspend () -> Response<Envelope<T>>): ApiResult<T> {
        val result = safeApiCall(block)
        if (result is ApiResult.Error && result.isUnauthorized) onUnauthorized()
        return result
    }

    /** Explicit logout: rotate-to-revoke server-side (best effort), then clear locally. */
    suspend fun logout() {
        tokenStore.session()?.let { s ->
            try {
                bareApi.refresh("Bearer ${s.token}") // discard the replacement — nobody holds it
            } catch (_: Exception) {
                // Offline logout is still a logout; token dies at expiry server-side.
            }
        }
        forceLogout(null)
    }

    private suspend fun forceLogout(reason: String?) {
        tokenStore.clear()
        _session.value = null
        _events.emit(AuthEvent.LoggedOut(reason))
    }

    companion object {
        /** Spec: refresh proactively within a configurable window of expiry. */
        const val REFRESH_WINDOW_MS = 5 * 60 * 1000L
    }
}
