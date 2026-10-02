package com.tempcompany.tempapp

import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.ClientUpdateRequest
import com.tempcompany.tempapp.data.api.EstimateCreateRequest
import com.tempcompany.tempapp.data.api.LoginRequest
import com.tempcompany.tempapp.data.api.safeApiCall
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Assume.assumeTrue
import org.junit.Before
import org.junit.Test
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory

/**
 * PHASE 2 TENANT-ISOLATION VERIFICATION, through the app's own network stack
 * (same Retrofit interface, DTOs, converter, and envelope handling the app
 * ships with). Mirrors the Phase 1 checklist requirement: two test companies,
 * neither can see the other's data — this time via the mobile client.
 *
 * Opt-in: runs only when the live dev API is reachable. Enable with
 *   INTEGRATION_BASE_URL=http://127.0.0.1:8085/api/v1/ ./gradlew :app:testDebugUnitTest
 * (Phase 1 dev server + test tenants alpha/bravo must be up.)
 */
class LiveApiIntegrationTest {

    private lateinit var api: ApiService
    private var bearer: String? = null

    private val baseUrl: String? = System.getenv("INTEGRATION_BASE_URL")

    @Before
    fun setUp() {
        assumeTrue("INTEGRATION_BASE_URL not set — skipping live API tests", !baseUrl.isNullOrBlank())
        val json = Json { ignoreUnknownKeys = true; explicitNulls = false; coerceInputValues = true }
        val client = OkHttpClient.Builder()
            .addInterceptor { chain ->
                val t = bearer
                val req = if (t != null && chain.request().header("Authorization") == null) {
                    chain.request().newBuilder().header("Authorization", "Bearer $t").build()
                } else chain.request()
                chain.proceed(req)
            }
            .build()
        api = Retrofit.Builder()
            .baseUrl(baseUrl!!)
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(ApiService::class.java)
    }

    private fun login(email: String, password: String): String = runBlocking {
        val r = safeApiCall { api.login(LoginRequest(email, password)) }
        assertTrue("login $email failed: $r", r is ApiResult.Success)
        (r as ApiResult.Success).data.token
    }

    @Test
    fun `tenant isolation - A cannot reach B's client by id`(): Unit = runBlocking {
        val tokenA = login("alpha-owner@test.local", "AlphaPass123!")
        val tokenB = login("bravo-owner@test.local", "BravoPass123!")

        // B's first client id, as B
        bearer = tokenB
        val bClients = safeApiCall { api.clients() }
        assertTrue(bClients is ApiResult.Success)
        val bClientId = (bClients as ApiResult.Success).data.items.first().id

        // Switch to A: read, update, and estimate-create against B's client must 404
        bearer = tokenA
        val get = safeApiCall { api.client(bClientId) }
        assertTrue(get is ApiResult.Error && (get as ApiResult.Error).httpCode == 404)

        val update = safeApiCall { api.updateClient(ClientUpdateRequest(id = bClientId, status = "won")) }
        assertTrue(update is ApiResult.Error && (update as ApiResult.Error).httpCode == 404)

        val create = safeApiCall {
            api.createEstimate(
                EstimateCreateRequest(
                    clientId = bClientId, homeSqft = 1500, stories = 1,
                    roofMaterialCurrent = "asphalt_3tab", roofMaterialDesired = "metal",
                    roofCondition = "good", pitch = "low", tearOff = "no",
                )
            )
        }
        assertTrue(create is ApiResult.Error && (create as ApiResult.Error).httpCode == 404)

        // A's own listing contains only A's tenants' records
        val aClients = safeApiCall { api.clients(perPage = 100) }
        assertTrue(aClients is ApiResult.Success)
        assertTrue((aClients as ApiResult.Success).data.items.none { it.id == bClientId })
    }

    @Test
    fun `token refresh rotates and kills the old token`(): Unit = runBlocking {
        val original = login("alpha-owner@test.local", "AlphaPass123!")

        val refreshed = safeApiCall { api.refresh("Bearer $original") }
        assertTrue(refreshed is ApiResult.Success)
        val newToken = (refreshed as ApiResult.Success).data.token
        assertEquals(64, newToken.length)

        // Old token must be dead (the app treats this 401 as logout, never retries)
        bearer = original
        val withOld = safeApiCall { api.companyProfile() }
        assertTrue(withOld is ApiResult.Error && (withOld as ApiResult.Error).httpCode == 401)

        // New token works and profile is tenant-correct
        bearer = newToken
        val withNew = safeApiCall { api.companyProfile() }
        assertTrue(withNew is ApiResult.Success)
        assertEquals("alpha-roofing-co", (withNew as ApiResult.Success).data.company.slug)
    }

    @Test
    fun `estimate create for own client works and marks mobile_app`(): Unit = runBlocking {
        bearer = login("alpha-owner@test.local", "AlphaPass123!")
        val own = safeApiCall { api.clients() }
        assumeTrue(own is ApiResult.Success && (own as ApiResult.Success).data.items.isNotEmpty())
        val clientId = (own as ApiResult.Success).data.items.first().id

        val created = safeApiCall {
            api.createEstimate(
                EstimateCreateRequest(
                    clientId = clientId, homeSqft = 2000, stories = 2,
                    roofMaterialCurrent = "asphalt_3tab", roofMaterialDesired = "asphalt_architectural",
                    roofAgeYears = 15, roofCondition = "fair", pitch = "medium", tearOff = "yes",
                )
            )
        }
        assertTrue("create failed: $created", created is ApiResult.Success)
        val data = (created as ApiResult.Success).data
        assertTrue(data.estimateHighCents > data.estimateLowCents)

        // It shows up in the estimates list as created_via=mobile_app
        val list = safeApiCall { api.estimates(clientId = clientId) }
        assertTrue(list is ApiResult.Success)
        val found = (list as ApiResult.Success).data.items.first { it.id == data.estimateId }
        assertEquals("mobile_app", found.createdVia)
    }

    @Test
    fun `invalid credentials return 401 with server message`(): Unit = runBlocking {
        val r = safeApiCall { api.login(LoginRequest("alpha-owner@test.local", "nope")) }
        assertTrue(r is ApiResult.Error)
        val e = r as ApiResult.Error
        assertEquals(401, e.httpCode)
        assertEquals("Invalid credentials.", e.message)
    }
}
