package com.tempcompany.tempapp.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * DTOs matching the FROZEN Phase 1 REST API byte-for-byte. Field shapes were
 * verified against captured live responses (app/src/test/resources/fixtures/).
 * PHP sends snake_case — every mapping is an explicit @SerialName, no convention
 * magic. Money is ALWAYS integer cents (Long); format only at display time.
 */

/** Uniform response envelope: {"success":true,"data":…} | {"success":false,"error":"…"} */
@Serializable
data class Envelope<T>(
    @SerialName("success") val success: Boolean,
    @SerialName("data") val data: T? = null,
    @SerialName("error") val error: String? = null,
)

// ── auth ──

@Serializable
data class LoginRequest(
    @SerialName("email") val email: String,
    @SerialName("password") val password: String,
)

@Serializable
data class LoginData(
    @SerialName("token") val token: String,
    @SerialName("expires_at") val expiresAt: String,
    @SerialName("user") val user: UserDto,
    @SerialName("company") val company: CompanySummaryDto,
)

@Serializable
data class RefreshData(
    @SerialName("token") val token: String,
    @SerialName("expires_at") val expiresAt: String,
)

@Serializable
data class UserDto(
    @SerialName("id") val id: Long,
    @SerialName("name") val name: String,
    @SerialName("email") val email: String,
    @SerialName("role") val role: String,
)

@Serializable
data class CompanySummaryDto(
    @SerialName("id") val id: Long,
    @SerialName("name") val name: String,
    @SerialName("slug") val slug: String,
)

// ── clients ──

@Serializable
data class ClientDto(
    @SerialName("id") val id: Long,
    @SerialName("name") val name: String,
    @SerialName("email") val email: String,
    @SerialName("phone") val phone: String,
    @SerialName("address") val address: String? = null,
    @SerialName("zip_code") val zipCode: String,
    @SerialName("state") val state: String,
    @SerialName("status") val status: String,
    @SerialName("notes") val notes: String? = null,            // get/update only
    @SerialName("consent_tcpa") val consentTcpa: Int = 0,
    @SerialName("estimates_count") val estimatesCount: Long? = null, // list only
    @SerialName("created_at") val createdAt: String,
)

@Serializable
data class PaginationDto(
    @SerialName("page") val page: Int,
    @SerialName("per_page") val perPage: Int,
    @SerialName("total") val total: Long,
    @SerialName("total_pages") val totalPages: Int,
)

@Serializable
data class ClientsListData(
    @SerialName("items") val items: List<ClientDto>,
    @SerialName("pagination") val pagination: PaginationDto,
)

@Serializable
data class ClientDetailData(
    @SerialName("client") val client: ClientDto,
    @SerialName("estimates") val estimates: List<EstimateDto>,
)

@Serializable
data class ClientUpdateRequest(
    @SerialName("id") val id: Long,
    @SerialName("status") val status: String? = null,  // omitted when null (explicitNulls=false)
    @SerialName("notes") val notes: String? = null,
)

@Serializable
data class ClientUpdateData(
    @SerialName("client") val client: ClientDto,
)

// ── estimates ──

@Serializable
data class EstimateDto(
    @SerialName("id") val id: Long,
    @SerialName("client_id") val clientId: Long? = null,       // list only
    @SerialName("client_name") val clientName: String? = null, // list only
    @SerialName("created_at") val createdAt: String,
    @SerialName("created_via") val createdVia: String,
    @SerialName("home_sqft") val homeSqft: Int,
    @SerialName("roof_sqft_estimate") val roofSqftEstimate: Int,
    @SerialName("stories") val stories: Int,
    @SerialName("roof_material_desired") val roofMaterialDesired: String,
    @SerialName("roof_condition") val roofCondition: String,
    @SerialName("pitch") val pitch: String,
    @SerialName("tear_off_needed") val tearOffNeeded: String,
    @SerialName("estimate_low_cents") val estimateLowCents: Long,
    @SerialName("estimate_high_cents") val estimateHighCents: Long,
    @SerialName("financing_monthly_cents") val financingMonthlyCents: Long? = null,
    @SerialName("results_token") val resultsToken: String,
)

@Serializable
data class EstimatesListData(
    @SerialName("items") val items: List<EstimateDto>,
    @SerialName("pagination") val pagination: PaginationDto,
)

/** Inline new-client payload for estimates/create.php. */
@Serializable
data class NewClientBody(
    @SerialName("name") val name: String,
    @SerialName("email") val email: String,
    @SerialName("phone") val phone: String,
    @SerialName("zip_code") val zipCode: String,
    @SerialName("state") val state: String,
    @SerialName("address") val address: String? = null,
    @SerialName("consent_tcpa") val consentTcpa: Int? = null,
)

@Serializable
data class EstimateCreateRequest(
    @SerialName("client_id") val clientId: Long? = null,
    @SerialName("client") val client: NewClientBody? = null,
    @SerialName("home_sqft") val homeSqft: Int,
    @SerialName("stories") val stories: Int,
    @SerialName("roof_material_current") val roofMaterialCurrent: String,
    @SerialName("roof_material_desired") val roofMaterialDesired: String,
    @SerialName("roof_age_years") val roofAgeYears: Int? = null,
    @SerialName("roof_condition") val roofCondition: String,
    @SerialName("pitch") val pitch: String,
    @SerialName("tear_off") val tearOff: String,
    @SerialName("send_email") val sendEmail: Boolean? = null,
)

@Serializable
data class EstimateCreateData(
    @SerialName("estimate_id") val estimateId: Long,
    @SerialName("client_id") val clientId: Long,
    @SerialName("results_token") val resultsToken: String,
    @SerialName("results_url") val resultsUrl: String,
    @SerialName("roof_sqft_estimate") val roofSqftEstimate: Int,
    @SerialName("estimate_low_cents") val estimateLowCents: Long,
    @SerialName("estimate_high_cents") val estimateHighCents: Long,
    @SerialName("financing_monthly_cents") val financingMonthlyCents: Long? = null,
)

// ── company ──

@Serializable
data class CompanyDto(
    @SerialName("id") val id: Long,
    @SerialName("slug") val slug: String,
    @SerialName("name") val name: String,
    @SerialName("logo_path") val logoPath: String? = null,
    @SerialName("contact_email") val contactEmail: String,
    @SerialName("contact_phone") val contactPhone: String,
    @SerialName("service_area") val serviceArea: String = "",
    @SerialName("license_number") val licenseNumber: String? = null,
    @SerialName("subscription_status") val subscriptionStatus: String,
    @SerialName("trial_ends_at") val trialEndsAt: String? = null,
    @SerialName("estimator_url") val estimatorUrl: String,
)

@Serializable
data class CompanyProfileData(
    @SerialName("company") val company: CompanyDto,
    @SerialName("pricing_settings") val pricingSettings: Map<String, String>,
    @SerialName("materials") val materials: Map<String, String>,
)

// ── device (Phase 2 additive endpoint) ──

@Serializable
data class RegisterDeviceRequest(
    @SerialName("fcm_token") val fcmToken: String,
    @SerialName("platform") val platform: String = "android",
)

@Serializable
data class RegisterDeviceData(
    @SerialName("registered") val registered: Boolean,
)

// ── enums fixed by the frozen contract (labels for materials come from company/profile.php) ──

object ApiEnums {
    val CLIENT_STATUSES = listOf("new", "contacted", "quoted", "won", "lost")
    val ROOF_CONDITIONS = listOf("good", "fair", "poor", "leaking", "unknown")
    val PITCHES = listOf("low", "medium", "steep", "unsure")
    val TEAR_OFF = listOf("yes", "no", "unsure")
    val CREATED_VIA = listOf("public_web", "dashboard", "mobile_app")
}
