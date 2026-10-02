package com.tempcompany.tempapp.data.api

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Query

/**
 * The frozen Phase 1 API. Relative paths — the base URL is BuildConfig.API_BASE_URL
 * (…/api/v1/), the ONLY place the backend domain exists.
 * Bearer auth is attached by AuthInterceptor; login is unauthenticated.
 */
interface ApiService {

    @POST("auth/login.php")
    suspend fun login(@Body body: LoginRequest): Response<Envelope<LoginData>>

    /**
     * Rotates the token; the presented token dies immediately. Called by AuthManager
     * with an explicit header on a bare client (no interceptor) to avoid recursion.
     */
    @POST("auth/refresh.php")
    suspend fun refresh(@Header("Authorization") bearer: String): Response<Envelope<RefreshData>>

    @GET("clients/list.php")
    suspend fun clients(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
        @Query("status") status: String? = null,
        @Query("q") q: String? = null,
    ): Response<Envelope<ClientsListData>>

    @GET("clients/get.php")
    suspend fun client(@Query("id") id: Long): Response<Envelope<ClientDetailData>>

    @POST("clients/update.php")
    suspend fun updateClient(@Body body: ClientUpdateRequest): Response<Envelope<ClientUpdateData>>

    @GET("estimates/list.php")
    suspend fun estimates(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
        @Query("client_id") clientId: Long? = null,
        @Query("created_via") createdVia: String? = null,
        @Query("roof_material_desired") roofMaterialDesired: String? = null,
        @Query("date_from") dateFrom: String? = null,
        @Query("date_to") dateTo: String? = null,
    ): Response<Envelope<EstimatesListData>>

    @POST("estimates/create.php")
    suspend fun createEstimate(@Body body: EstimateCreateRequest): Response<Envelope<EstimateCreateData>>

    @GET("company/profile.php")
    suspend fun companyProfile(): Response<Envelope<CompanyProfileData>>

    /** Phase 2 additive endpoint (see the API table in README.md) — FCM token registration. */
    @POST("device/register-token.php")
    suspend fun registerDevice(@Body body: RegisterDeviceRequest): Response<Envelope<RegisterDeviceData>>
}
