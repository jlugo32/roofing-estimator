package com.tempcompany.tempapp.data.api

import kotlinx.serialization.json.Json
import retrofit2.Response
import java.io.IOException

/**
 * Every network call in the app resolves to this — no silent failures, no raw
 * exceptions reaching the UI. Loading is UI state, not a result.
 */
sealed interface ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>
    data class Error(
        val message: String,
        val httpCode: Int? = null,
        val isNetwork: Boolean = false,
    ) : ApiResult<Nothing> {
        val isUnauthorized: Boolean get() = httpCode == 401
    }
}

private val errorJson = Json { ignoreUnknownKeys = true }

/**
 * Executes a Retrofit call against the {success,data,error} envelope:
 * - 2xx + success:true + data → Success
 * - HTTP error → the server's own error message out of the JSON body (the API
 *   always sends one: suspended-company 403s, tenant 404s, validation 422s…)
 * - IOException → network Error with a friendly message and isNetwork=true
 */
suspend fun <T> safeApiCall(block: suspend () -> Response<Envelope<T>>): ApiResult<T> {
    val response = try {
        block()
    } catch (e: IOException) {
        return ApiResult.Error("No connection. Check your network and try again.", isNetwork = true)
    } catch (e: Exception) {
        return ApiResult.Error("Unexpected error: ${e.message ?: e.javaClass.simpleName}")
    }

    val body = response.body()
    if (response.isSuccessful && body != null && body.success && body.data != null) {
        return ApiResult.Success(body.data)
    }

    val serverMessage = body?.error ?: run {
        val raw = try { response.errorBody()?.string() } catch (_: IOException) { null }
        raw?.let {
            try {
                errorJson.decodeFromString<Envelope<Unit>>(it).error
            } catch (_: Exception) {
                null
            }
        }
    }
    return ApiResult.Error(
        message = serverMessage ?: "Request failed (HTTP ${response.code()}).",
        httpCode = response.code(),
    )
}
