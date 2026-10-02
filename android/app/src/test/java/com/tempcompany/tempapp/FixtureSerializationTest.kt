package com.tempcompany.tempapp

import com.tempcompany.tempapp.data.api.ClientDetailData
import com.tempcompany.tempapp.data.api.ClientUpdateData
import com.tempcompany.tempapp.data.api.ClientsListData
import com.tempcompany.tempapp.data.api.CompanyProfileData
import com.tempcompany.tempapp.data.api.Envelope
import com.tempcompany.tempapp.data.api.EstimateCreateData
import com.tempcompany.tempapp.data.api.EstimatesListData
import com.tempcompany.tempapp.data.api.LoginData
import com.tempcompany.tempapp.data.api.RefreshData
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * The fixtures are REAL responses captured from the running Phase 1 API
 * (not hand-written) — if these decode, the DTOs match the frozen contract.
 */
class FixtureSerializationTest {

    private val json = Json { ignoreUnknownKeys = true; explicitNulls = false; coerceInputValues = true }

    private fun fixture(name: String): String =
        javaClass.classLoader!!.getResourceAsStream("fixtures/$name")!!.bufferedReader().readText()

    @Test
    fun `login response decodes`() {
        val env = json.decodeFromString<Envelope<LoginData>>(fixture("login.json"))
        assertTrue(env.success)
        val d = env.data!!
        assertEquals(64, d.token.length)
        assertEquals("owner", d.user.role)
        assertEquals("alpha-roofing-co", d.company.slug)
        assertTrue(d.expiresAt.matches(Regex("""\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}""")))
    }

    @Test
    fun `refresh response decodes`() {
        val env = json.decodeFromString<Envelope<RefreshData>>(fixture("refresh.json"))
        assertEquals(64, env.data!!.token.length)
    }

    @Test
    fun `clients list decodes with pagination and estimates_count`() {
        val env = json.decodeFromString<Envelope<ClientsListData>>(fixture("clients-list.json"))
        val d = env.data!!
        assertTrue(d.items.isNotEmpty())
        assertNotNull(d.items.first().estimatesCount)
        assertTrue(d.pagination.total >= d.items.size)
    }

    @Test
    fun `client detail decodes with nullable notes and estimate history`() {
        val env = json.decodeFromString<Envelope<ClientDetailData>>(fixture("clients-get.json"))
        val d = env.data!!
        assertEquals("TX", d.client.state)
        assertTrue(d.estimates.isNotEmpty())
        assertTrue(d.estimates.first().estimateLowCents > 0)
    }

    @Test
    fun `client update decodes`() {
        val env = json.decodeFromString<Envelope<ClientUpdateData>>(fixture("clients-update.json"))
        assertEquals("sample note", env.data!!.client.notes)
    }

    @Test
    fun `estimates list decodes with client_name`() {
        val env = json.decodeFromString<Envelope<EstimatesListData>>(fixture("estimates-list.json"))
        val first = env.data!!.items.first()
        assertNotNull(first.clientName)
        assertTrue(first.estimateHighCents > first.estimateLowCents)
    }

    @Test
    fun `estimate create decodes`() {
        val env = json.decodeFromString<Envelope<EstimateCreateData>>(fixture("estimates-create.json"))
        val d = env.data!!
        assertEquals(32, d.resultsToken.length)
        assertTrue(d.resultsUrl.contains(d.resultsToken))
    }

    @Test
    fun `company profile decodes with pricing map and materials map`() {
        val env = json.decodeFromString<Envelope<CompanyProfileData>>(fixture("company-profile.json"))
        val d = env.data!!
        assertEquals("alpha-roofing-co", d.company.slug)
        assertTrue(d.pricingSettings.containsKey("waste_factor_pct"))
        assertTrue(d.materials.containsKey("asphalt_architectural"))
        // nullable fields present-as-null in the real response
        assertEquals(null, d.company.logoPath)
        assertEquals(null, d.company.licenseNumber)
    }

    @Test
    fun `error envelope decodes`() {
        val env = json.decodeFromString<Envelope<LoginData>>(fixture("error-401.json"))
        assertFalse(env.success)
        assertEquals("Invalid API token.", env.error)
        assertEquals(null, env.data)
    }
}
