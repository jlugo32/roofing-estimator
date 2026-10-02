package com.tempcompany.tempapp.ui.screens.dashboard

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.Button
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.windowsizeclass.WindowSizeClass
import androidx.compose.material3.windowsizeclass.WindowWidthSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.auth.AuthManager
import com.tempcompany.tempapp.data.repo.StatsRepository
import com.tempcompany.tempapp.ui.components.EmptyState
import com.tempcompany.tempapp.ui.components.ErrorState
import com.tempcompany.tempapp.ui.components.LoadingState
import com.tempcompany.tempapp.ui.components.SectionCard
import com.tempcompany.tempapp.ui.components.StatCard
import com.tempcompany.tempapp.ui.components.StatusBadge
import com.tempcompany.tempapp.ui.nav.Routes
import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.formatCentsRange
import com.tempcompany.tempapp.util.relativeLabel
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.util.Locale
import javax.inject.Inject

data class DashboardUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val stats: StatsRepository.DashboardStats? = null,
)

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val statsRepository: StatsRepository,
    val authManager: AuthManager,
) : ViewModel() {

    private val _state = MutableStateFlow(DashboardUiState())
    val state: StateFlow<DashboardUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            when (val r = statsRepository.dashboardStats()) {
                is ApiResult.Success -> _state.value = DashboardUiState(loading = false, stats = r.data)
                is ApiResult.Error -> _state.value = _state.value.copy(loading = false, error = r.message)
            }
        }
    }
}

/** Home: stat cards mirroring the web dashboard + recent activity. */
@Composable
fun DashboardScreen(
    navController: NavController,
    windowSizeClass: WindowSizeClass,
    viewModel: DashboardViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val session by viewModel.authManager.session.collectAsState()

    when {
        state.loading && state.stats == null -> LoadingState()
        state.error != null && state.stats == null -> ErrorState(state.error!!, onRetry = viewModel::load)
        else -> {
            val stats = state.stats ?: return
            val compact = windowSizeClass.widthSizeClass == WindowWidthSizeClass.Compact
            val approx = if (stats.capped) "approx — large dataset" else null

            LazyColumn(
                modifier = Modifier.fillMaxSize().widthIn(max = 900.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                item {
                    Column {
                        Text(
                            session?.companyName ?: "",
                            style = MaterialTheme.typography.headlineMedium,
                            color = MaterialTheme.colorScheme.primary,
                        )
                        session?.userName?.takeIf { it.isNotBlank() }?.let {
                            Text(
                                "Welcome back, ${it.substringBefore(' ')}",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }

                // Stat grid: 2 columns on phones, 4 on wider screens.
                val cards = listOf(
                    Triple("New leads (7d)", stats.newLeadsThisWeek.toString(), approx),
                    Triple("Total clients", stats.totalClients.toString(), null),
                    Triple("Estimates this month", stats.estimatesThisMonth.toString(), approx),
                    Triple("Conversion", String.format(Locale.US, "%.1f%%", stats.conversionRatePct), approx),
                )
                items(cards.chunked(if (compact) 2 else 4).size) { rowIndex ->
                    val row = cards.chunked(if (compact) 2 else 4)[rowIndex]
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        row.forEach { (label, value, caption) ->
                            StatCard(label = label, value = value, caption = caption, modifier = Modifier.weight(1f))
                        }
                    }
                }
                item {
                    StatCard(
                        label = "Avg estimate value",
                        value = formatCents(stats.avgEstimateCents),
                        caption = approx,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }

                item {
                    Button(
                        onClick = { navController.navigate(Routes.createEstimate()) },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("New estimate") }
                }

                item {
                    SectionCard(title = "Recent estimates") {
                        if (stats.recentEstimates.isEmpty()) {
                            Text(
                                "No estimates yet — share your estimator link to get your first lead.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        } else {
                            stats.recentEstimates.forEachIndexed { i, e ->
                                if (i > 0) HorizontalDivider(Modifier.padding(vertical = 8.dp))
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable(enabled = e.clientId != null) {
                                            e.clientId?.let { navController.navigate(Routes.clientDetail(it)) }
                                        },
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Column(Modifier.weight(1f)) {
                                        Text(e.clientName ?: "Client", style = MaterialTheme.typography.titleMedium)
                                        Text(
                                            "${viaLabel(e.createdVia)} · ${relativeLabel(e.createdAt)}",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    Text(
                                        formatCentsRange(e.estimateLowCents, e.estimateHighCents),
                                        style = MaterialTheme.typography.bodyLarge,
                                        fontWeight = FontWeight.SemiBold,
                                        color = MaterialTheme.colorScheme.primary,
                                    )
                                }
                            }
                        }
                    }
                }

                item {
                    SectionCard(title = "New clients") {
                        if (stats.recentClients.isEmpty()) {
                            Text(
                                "Leads captured by your public estimator appear here.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        } else {
                            stats.recentClients.forEachIndexed { i, c ->
                                if (i > 0) HorizontalDivider(Modifier.padding(vertical = 8.dp))
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clickable { navController.navigate(Routes.clientDetail(c.id)) },
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Column(Modifier.weight(1f)) {
                                        Text(c.name, style = MaterialTheme.typography.titleMedium)
                                        Text(
                                            relativeLabel(c.createdAt),
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    StatusBadge(c.status)
                                }
                            }
                        }
                    }
                }

                item { Spacer(Modifier.padding(bottom = 8.dp)) }
            }
        }
    }
}

private fun viaLabel(via: String): String = when (via) {
    "public_web" -> "Web estimator"
    "dashboard" -> "Dashboard"
    "mobile_app" -> "Field app"
    else -> via
}
