package com.tempcompany.tempapp.data.api

import com.tempcompany.tempapp.data.auth.AuthManager
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Attaches "Authorization: Bearer …" to every request on the authed client and
 * gives AuthManager a chance to proactively refresh first. login/refresh go
 * through the bare client and never pass here, so no recursion is possible.
 */
@Singleton
class AuthInterceptor @Inject constructor(
    private val authManager: dagger.Lazy<AuthManager>,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val manager = authManager.get()
        // OkHttp interceptors are synchronous by design; refresh is rare and fast.
        runBlocking { manager.ensureFreshToken() }

        val token = manager.currentToken()
        val request = if (token != null) {
            chain.request().newBuilder()
                .header("Authorization", "Bearer $token")
                .build()
        } else {
            chain.request()
        }
        return chain.proceed(request)
    }
}
