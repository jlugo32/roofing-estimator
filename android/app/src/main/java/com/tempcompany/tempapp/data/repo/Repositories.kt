package com.tempcompany.tempapp.data.repo

import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.ClientDetailData
import com.tempcompany.tempapp.data.api.ClientUpdateData
import com.tempcompany.tempapp.data.api.ClientUpdateRequest
import com.tempcompany.tempapp.data.api.ClientsListData
import com.tempcompany.tempapp.data.api.CompanyProfileData
import com.tempcompany.tempapp.data.api.EstimateCreateData
import com.tempcompany.tempapp.data.api.EstimateCreateRequest
import com.tempcompany.tempapp.data.api.EstimatesListData
import com.tempcompany.tempapp.data.auth.AuthManager
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Thin repositories over the frozen API. All calls run through
 * AuthManager.authedCall so the 401→logout policy is impossible to skip.
 * Tenant scoping happens SERVER-SIDE from the Bearer token — the app never
 * sends a company id.
 */

@Singleton
class ClientsRepository @Inject constructor(
    private val api: ApiService,
    private val auth: AuthManager,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        query: String? = null,
    ): ApiResult<ClientsListData> =
        auth.authedCall { api.clients(page, perPage, status, query?.takeIf { it.isNotBlank() }) }

    suspend fun get(id: Long): ApiResult<ClientDetailData> =
        auth.authedCall { api.client(id) }

    suspend fun update(id: Long, status: String? = null, notes: String? = null): ApiResult<ClientUpdateData> =
        auth.authedCall { api.updateClient(ClientUpdateRequest(id = id, status = status, notes = notes)) }
}

@Singleton
class EstimatesRepository @Inject constructor(
    private val api: ApiService,
    private val auth: AuthManager,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        clientId: Long? = null,
        createdVia: String? = null,
        material: String? = null,
        dateFrom: String? = null,
        dateTo: String? = null,
    ): ApiResult<EstimatesListData> =
        auth.authedCall { api.estimates(page, perPage, clientId, createdVia, material, dateFrom, dateTo) }

    suspend fun create(request: EstimateCreateRequest): ApiResult<EstimateCreateData> =
        auth.authedCall { api.createEstimate(request) }
}

@Singleton
class CompanyRepository @Inject constructor(
    private val api: ApiService,
    private val auth: AuthManager,
) {
    suspend fun profile(): ApiResult<CompanyProfileData> =
        auth.authedCall { api.companyProfile() }
}
