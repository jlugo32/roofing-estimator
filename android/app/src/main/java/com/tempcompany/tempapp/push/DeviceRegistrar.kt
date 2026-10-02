package com.tempcompany.tempapp.push

import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.RegisterDeviceRequest
import com.tempcompany.tempapp.data.auth.AuthManager
import com.google.android.gms.tasks.Tasks
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Registers this device's FCM token with the backend (Phase 2 additive endpoint
 * /api/v1/device/register-token.php) so the company gets new-lead pushes.
 *
 * Fully guarded: without google-services.json + a Firebase project this is a
 * silent no-op — the app works identically minus push. See docs/SETUP.md "FCM setup".
 */
@Singleton
class DeviceRegistrar @Inject constructor(
    private val api: ApiService,
    private val auth: AuthManager,
) {
    suspend fun registerIfPossible() {
        if (!auth.isLoggedIn()) return
        val token = try {
            withContext(Dispatchers.IO) { Tasks.await(FirebaseMessaging.getInstance().token) }
        } catch (_: Exception) {
            null // Firebase not initialized (no google-services.json) — fine
        } ?: return

        try {
            api.registerDevice(RegisterDeviceRequest(fcmToken = token))
        } catch (_: Exception) {
            // Best-effort: push registration must never break login.
        }
    }
}
