package com.tempcompany.tempapp.ui.screens.clients

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
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.material3.windowsizeclass.WindowSizeClass
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiEnums
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ClientDto
import com.tempcompany.tempapp.data.repo.ClientsRepository
import com.tempcompany.tempapp.ui.components.EmptyState
import com.tempcompany.tempapp.ui.components.ErrorState
import com.tempcompany.tempapp.ui.components.LoadingState
import com.tempcompany.tempapp.ui.components.StatusBadge
import com.tempcompany.tempapp.ui.nav.Routes
import com.tempcompany.tempapp.util.relativeLabel
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ClientsUiState(
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val error: String? = null,
    val items: List<ClientDto> = emptyList(),
    val page: Int = 1,
    val totalPages: Int = 1,
    val total: Long = 0,
    val search: String = "",
    val status: String? = null, // null = all
)

@HiltViewModel
class ClientsViewModel @Inject constructor(
    private val clientsRepository: ClientsRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(ClientsUiState())
    val state: StateFlow<ClientsUiState> = _state.asStateFlow()

    private var searchJob: Job? = null

    init {
        reload()
    }

    fun onSearch(text: String) {
        _state.value = _state.value.copy(search = text)
        searchJob?.cancel()
        searchJob = viewModelScope.launch {
            delay(400) // debounce
            reload()
        }
    }

    fun onStatus(status: String?) {
        _state.value = _state.value.copy(status = status)
        reload()
    }

    fun reload(pullRefresh: Boolean = false) {
        val s = _state.value
        _state.value = s.copy(loading = !pullRefresh, refreshing = pullRefresh, error = null)
        viewModelScope.launch {
            when (val r = clientsRepository.list(page = 1, status = s.status, query = s.search)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    loading = false, refreshing = false,
                    items = r.data.items,
                    page = 1,
                    totalPages = r.data.pagination.totalPages,
                    total = r.data.pagination.total,
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
            when (val r = clientsRepository.list(page = next, status = s.status, query = s.search)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    loadingMore = false,
                    items = _state.value.items + r.data.items,
                    page = next,
                    totalPages = r.data.pagination.totalPages,
                )
                // Silent-ish failure is not allowed; surface via error banner but keep the list.
                is ApiResult.Error -> _state.value = _state.value.copy(loadingMore = false, error = r.message)
            }
        }
    }
}

/** Lead/client list: search, status filter, infinite scroll, pull-to-refresh. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClientsScreen(
    navController: NavController,
    windowSizeClass: WindowSizeClass,
    viewModel: ClientsViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val listState = rememberLazyListState()

    // Infinite scroll: request the next page when the tail comes into view.
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
                "Clients & Leads",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(top = 16.dp),
            )

            OutlinedTextField(
                value = state.search,
                onValueChange = viewModel::onSearch,
                placeholder = { Text("Search name, email, or phone") },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
            )

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                FilterChip(
                    selected = state.status == null,
                    onClick = { viewModel.onStatus(null) },
                    label = { Text("All") },
                )
                ApiEnums.CLIENT_STATUSES.forEach { status ->
                    FilterChip(
                        selected = state.status == status,
                        onClick = { viewModel.onStatus(status) },
                        label = { Text(status.replaceFirstChar { it.uppercase() }) },
                    )
                }
            }

            when {
                state.loading && state.items.isEmpty() -> LoadingState()
                state.error != null && state.items.isEmpty() ->
                    ErrorState(state.error!!, onRetry = { viewModel.reload() })
                state.items.isEmpty() -> EmptyState(
                    title = if (state.search.isBlank() && state.status == null) "No clients yet" else "No matches",
                    caption = if (state.search.isBlank() && state.status == null)
                        "Leads from your public estimator will appear here."
                    else "Try a different search or filter.",
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
                        if (state.error != null) {
                            item {
                                Text(
                                    state.error!!,
                                    color = MaterialTheme.colorScheme.error,
                                    style = MaterialTheme.typography.bodyMedium,
                                )
                            }
                        }
                        items(state.items.size, key = { state.items[it].id }) { index ->
                            val client = state.items[index]
                            ClientRow(client) { navController.navigate(Routes.clientDetail(client.id)) }
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
private fun ClientRow(client: ClientDto, onClick: () -> Unit) {
    Card(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(client.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Text(
                    client.email,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    "${client.phone} · ${client.zipCode}, ${client.state}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                StatusBadge(client.status)
                client.estimatesCount?.let {
                    Text(
                        "$it estimate${if (it == 1L) "" else "s"}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 4.dp),
                    )
                }
                Text(
                    relativeLabel(client.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
