package com.tempcompany.tempapp.ui.screens.estimates

import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.windowsizeclass.WindowSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.EstimateDto
import com.tempcompany.tempapp.data.repo.CompanyRepository
import com.tempcompany.tempapp.data.repo.EstimatesRepository
import com.tempcompany.tempapp.ui.components.EmptyState
import com.tempcompany.tempapp.ui.components.ErrorState
import com.tempcompany.tempapp.ui.components.LoadingState
import com.tempcompany.tempapp.ui.nav.Routes
import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.formatCentsRange
import com.tempcompany.tempapp.util.relativeLabel
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.launch
import javax.inject.Inject

data class EstimatesUiState(
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val error: String? = null,
    val items: List<EstimateDto> = emptyList(),
    val page: Int = 1,
    val totalPages: Int = 1,
    val material: String? = null,    // key, null = all
    val createdVia: String? = null,  // null = all
    val materials: Map<String, String> = emptyMap(), // key → label from company profile
)

@HiltViewModel
class EstimatesViewModel @Inject constructor(
    private val estimatesRepository: EstimatesRepository,
    private val companyRepository: CompanyRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(EstimatesUiState())
    val state: StateFlow<EstimatesUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            // Material labels come from the company profile; filters still work without them.
            when (val r = companyRepository.profile()) {
                is ApiResult.Success -> _state.value = _state.value.copy(materials = r.data.materials)
                is ApiResult.Error -> Unit
            }
        }
        reload()
    }

    fun onMaterial(key: String?) {
        _state.value = _state.value.copy(material = key)
        reload()
    }

    fun onVia(via: String?) {
        _state.value = _state.value.copy(createdVia = via)
        reload()
    }

    fun reload(pullRefresh: Boolean = false) {
        val s = _state.value
        _state.value = s.copy(loading = !pullRefresh, refreshing = pullRefresh, error = null)
        viewModelScope.launch {
            when (val r = estimatesRepository.list(page = 1, createdVia = s.createdVia, material = s.material)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    loading = false, refreshing = false,
                    items = r.data.items, page = 1, totalPages = r.data.pagination.totalPages,
                )
                is ApiResult.Error -> _state.value = _state.value.copy(
                    loading = false, refreshing = false, error = r.message,
                )
            }
        }
    }

    fun loadMore() {
        val s = _state.value
        if (s.loadingMore || s.loading || s.page >= s.totalPages) return
        _state.value = s.copy(loadingMore = true)
        viewModelScope.launch {
            val next = s.page + 1
            when (val r = estimatesRepository.list(page = next, createdVia = s.createdVia, material = s.material)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    loadingMore = false,
                    items = _state.value.items + r.data.items,
                    page = next,
                    totalPages = r.data.pagination.totalPages,
                )
                is ApiResult.Error -> _state.value = _state.value.copy(loadingMore = false, error = r.message)
            }
        }
    }
}

/** All estimates for the company: filterable, paginated. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EstimatesScreen(
    navController: NavController,
    windowSizeClass: WindowSizeClass,
    viewModel: EstimatesViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val listState = rememberLazyListState()
    var materialMenuOpen by remember { mutableStateOf(false) }

    LaunchedEffect(listState) {
        snapshotFlow {
            val last = listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0
            val total = listState.layoutInfo.totalItemsCount
            total > 0 && last >= total - 4
        }
            .distinctUntilChanged()
            .collect { nearEnd -> if (nearEnd) viewModel.loadMore() }
    }

    Scaffold(
        floatingActionButton = {
            FloatingActionButton(onClick = { navController.navigate(Routes.createEstimate()) }) {
                Icon(Icons.Filled.Add, contentDescription = "New estimate")
            }
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp)) {
            Text(
                "Estimates",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(top = 16.dp),
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box {
                    OutlinedButton(onClick = { materialMenuOpen = true }) {
                        Text(state.material?.let { state.materials[it] ?: it } ?: "All materials")
                        Icon(Icons.Filled.ArrowDropDown, contentDescription = null)
                    }
                    DropdownMenu(expanded = materialMenuOpen, onDismissRequest = { materialMenuOpen = false }) {
                        DropdownMenuItem(
                            text = { Text("All materials") },
                            onClick = { materialMenuOpen = false; viewModel.onMaterial(null) },
                        )
                        state.materials.forEach { (key, label) ->
                            DropdownMenuItem(
                                text = { Text(label) },
                                onClick = { materialMenuOpen = false; viewModel.onMaterial(key) },
                            )
                        }
                    }
                }
                FilterChip(
                    selected = state.createdVia == null,
                    onClick = { viewModel.onVia(null) },
                    label = { Text("All sources") },
                )
                listOf("public_web" to "Web", "dashboard" to "Dashboard", "mobile_app" to "App").forEach { (via, label) ->
                    FilterChip(
                        selected = state.createdVia == via,
                        onClick = { viewModel.onVia(via) },
                        label = { Text(label) },
                    )
                }
            }

            when {
                state.loading && state.items.isEmpty() -> LoadingState()
                state.error != null && state.items.isEmpty() ->
                    ErrorState(state.error!!, onRetry = { viewModel.reload() })
                state.items.isEmpty() -> EmptyState(
                    title = "No estimates",
                    caption = "Estimates from your public estimator and this app will appear here.",
                )
                else -> PullToRefreshBox(
                    isRefreshing = state.refreshing,
                    onRefresh = { viewModel.reload(pullRefresh = true) },
                    modifier = Modifier.fillMaxSize(),
                ) {
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize().widthIn(max = 840.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(bottom = 88.dp),
                    ) {
                        items(state.items.size, key = { state.items[it].id }) { index ->
                            val e = state.items[index]
                            EstimateRow(
                                e = e,
                                materialLabel = state.materials[e.roofMaterialDesired]
                                    ?: e.roofMaterialDesired.replace('_', ' '),
                                onClick = { e.clientId?.let { navController.navigate(Routes.clientDetail(it)) } },
                            )
                        }
                        if (state.loadingMore) {
                            item {
                                Box(Modifier.fillMaxWidth().padding(12.dp), contentAlignment = Alignment.Center) {
                                    CircularProgressIndicator(strokeWidth = 2.dp)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun EstimateRow(e: EstimateDto, materialLabel: String, onClick: () -> Unit) {
    Card(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Column(Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    formatCentsRange(e.estimateLowCents, e.estimateHighCents),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    relativeLabel(e.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text(e.clientName ?: "Client", style = MaterialTheme.typography.bodyLarge)
            Text(
                "$materialLabel · ${e.roofSqftEstimate} sq ft roof est · ${sourceLabel(e.createdVia)}",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            e.financingMonthlyCents?.takeIf { it > 0 }?.let {
                Text(
                    "from ${formatCents(it)}/mo financing",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.secondary,
                )
            }
        }
    }
}

private fun sourceLabel(via: String): String = when (via) {
    "public_web" -> "Web estimator"
    "dashboard" -> "Dashboard"
    "mobile_app" -> "Field app"
    else -> via
}
