package com.tempcompany.tempapp.data.auth

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.tempcompany.tempapp.util.parseServerDateTime
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * API token + session identity at rest, in EncryptedSharedPreferences backed by
 * an Android Keystore master key. NEVER plaintext SharedPreferences (spec rule).
 */
@Singleton
class TokenStore @Inject constructor(@ApplicationContext context: Context) {

    private val prefs: SharedPreferences by lazy {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            context,
            "secure_session",
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    data class Session(
        val token: String,
        val expiresAtEpochMs: Long,
        val userName: String,
        val userEmail: String,
        val userRole: String,
        val companyName: String,
        val companySlug: String,
    )

    @Synchronized
    fun save(
        token: String,
        expiresAt: String, // server format "yyyy-MM-dd HH:mm:ss"
        userName: String? = null,
        userEmail: String? = null,
        userRole: String? = null,
        companyName: String? = null,
        companySlug: String? = null,
    ) {
        prefs.edit().apply {
            putString(KEY_TOKEN, token)
            putLong(KEY_EXPIRES, parseServerDateTime(expiresAt).toEpochMilli())
            userName?.let { putString(KEY_USER_NAME, it) }
            userEmail?.let { putString(KEY_USER_EMAIL, it) }
            userRole?.let { putString(KEY_USER_ROLE, it) }
            companyName?.let { putString(KEY_COMPANY_NAME, it) }
            companySlug?.let { putString(KEY_COMPANY_SLUG, it) }
        }.apply()
    }

    @Synchronized
    fun session(): Session? {
        val token = prefs.getString(KEY_TOKEN, null) ?: return null
        return Session(
            token = token,
            expiresAtEpochMs = prefs.getLong(KEY_EXPIRES, 0L),
            userName = prefs.getString(KEY_USER_NAME, "") ?: "",
            userEmail = prefs.getString(KEY_USER_EMAIL, "") ?: "",
            userRole = prefs.getString(KEY_USER_ROLE, "staff") ?: "staff",
            companyName = prefs.getString(KEY_COMPANY_NAME, "") ?: "",
            companySlug = prefs.getString(KEY_COMPANY_SLUG, "") ?: "",
        )
    }

    @Synchronized
    fun clear() {
        prefs.edit().clear().apply()
    }

    private companion object {
        const val KEY_TOKEN = "api_token"
        const val KEY_EXPIRES = "api_token_expires_ms"
        const val KEY_USER_NAME = "user_name"
        const val KEY_USER_EMAIL = "user_email"
        const val KEY_USER_ROLE = "user_role"
        const val KEY_COMPANY_NAME = "company_name"
        const val KEY_COMPANY_SLUG = "company_slug"
    }
}
