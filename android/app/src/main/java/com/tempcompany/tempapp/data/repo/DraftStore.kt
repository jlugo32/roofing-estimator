package com.tempcompany.tempapp.data.repo

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.tempcompany.tempapp.data.api.EstimateCreateRequest
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.first
import kotlinx.serialization.json.Json
import javax.inject.Inject
import javax.inject.Singleton

private val Context.draftDataStore by preferencesDataStore(name = "drafts")

/**
 * Spec rule: never silently drop a submitted estimate. The Create Estimate form
 * persists here (DataStore, non-secret) the moment the rep taps submit, and is
 * cleared only after the server confirms — a failed request survives app death
 * and can be retried without re-entering data.
 */
@Singleton
class DraftStore @Inject constructor(@ApplicationContext private val context: Context) {

    private val json = Json { ignoreUnknownKeys = true; explicitNulls = false }
    private val key = stringPreferencesKey("pending_estimate_draft")

    suspend fun saveEstimateDraft(request: EstimateCreateRequest) {
        context.draftDataStore.edit { it[key] = json.encodeToString(EstimateCreateRequest.serializer(), request) }
    }

    suspend fun pendingEstimateDraft(): EstimateCreateRequest? =
        context.draftDataStore.data.first()[key]?.let {
            runCatching { json.decodeFromString(EstimateCreateRequest.serializer(), it) }.getOrNull()
        }

    suspend fun clearEstimateDraft() {
        context.draftDataStore.edit { it.remove(key) }
    }
}
