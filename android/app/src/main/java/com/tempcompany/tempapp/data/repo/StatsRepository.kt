package com.tempcompany.tempapp.data.repo

import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.ClientDto
import com.tempcompany.tempapp.data.api.EstimateDto
import com.tempcompany.tempapp.data.auth.AuthManager
import com.tempcompany.tempapp.util.parseServerDateTime
import java.time.Instant
import java.time.ZoneId
import java.time.temporal.ChronoUnit
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Dashboard stat cards, computed CLIENT-SIDE from the frozen list endpoints —
 * the Phase 1 API deliberately has no stats endpoint and may not be modified.
 *
 * KNOWN LIMIT: we walk up to [MAX_PAGES] × [PER_PAGE] records per collection
 * (500 clients / 500 estimates). Under the cap the numbers match the web
 * dashboard exactly; over it, [DashboardStats.capped] is true and the UI shows
 * the numbers as approximate. A server-side /api/v1/stats.php is the flagged
 * Phase 3 fix (see docs/SETUP.md "Known limits").
 */
@Singleton
class StatsRepository @Inject constructor(
    private val api: ApiService,
    private val auth: AuthManager,
) {

    data class DashboardStats(
        val totalClients: Long,
        val newLeadsThisWeek: Int,
        val conversionRatePct: Double,
        val avgEstimateCents: Long,
        val estimatesThisMonth: Int,
        val recentEstimates: List<EstimateDto>,
        val recentClients: List<ClientDto>,
        val capped: Boolean,
    )

    suspend fun dashboardStats(): ApiResult<DashboardStats> {
        val clients = mutableListOf<ClientDto>()
        var totalClients = 0L
        var capped = false

        var page = 1
        while (true) {
            when (val r = auth.authedCall { api.clients(page = page, perPage = PER_PAGE) }) {
                is ApiResult.Error -> return r
                is ApiResult.Success -> {
                    clients += r.data.items
                    totalClients = r.data.pagination.total
                    if (page >= r.data.pagination.totalPages) break
                    if (page >= MAX_PAGES) { capped = true; break }
                    page++
                }
            }
        }

        val estimates = mutableListOf<EstimateDto>()
        page = 1
        while (true) {
            when (val r = auth.authedCall { api.estimates(page = page, perPage = PER_PAGE) }) {
                is ApiResult.Error -> return r
                is ApiResult.Success -> {
                    estimates += r.data.items
                    if (page >= r.data.pagination.totalPages) break
                    if (page >= MAX_PAGES) { capped = true; break }
                    page++
                }
            }
        }

        val now = Instant.now()
        val weekAgo = now.minus(7, ChronoUnit.DAYS)
        val serverZone = ZoneId.of("America/New_York")
        val thisMonth = now.atZone(serverZone).let { it.year to it.monthValue }

        val newThisWeek = clients.count { runCatching { parseServerDateTime(it.createdAt).isAfter(weekAgo) }.getOrDefault(false) }
        val won = clients.count { it.status == "won" }
        val conversion = if (clients.isNotEmpty()) won * 100.0 / clients.size else 0.0
        val avg = if (estimates.isNotEmpty()) {
            estimates.sumOf { (it.estimateLowCents + it.estimateHighCents) / 2 } / estimates.size
        } else 0L
        val monthCount = estimates.count {
            runCatching {
                val z = parseServerDateTime(it.createdAt).atZone(serverZone)
                (z.year to z.monthValue) == thisMonth
            }.getOrDefault(false)
        }

        return ApiResult.Success(
            DashboardStats(
                totalClients = totalClients,
                newLeadsThisWeek = newThisWeek,
                conversionRatePct = conversion,
                avgEstimateCents = avg,
                estimatesThisMonth = monthCount,
                recentEstimates = estimates.take(8),
                recentClients = clients.take(5),
                capped = capped,
            )
        )
    }

    private companion object {
        const val PER_PAGE = 100 // API max
        const val MAX_PAGES = 5
    }
}
