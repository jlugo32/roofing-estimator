package com.tempcompany.tempapp.ui.screens.clients

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiEnums
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ClientDto
import com.tempcompany.tempapp.data.api.EstimateDto
import com.tempcompany.tempapp.data.repo.ClientsRepository
import com.tempcompany.tempapp.ui.components.ErrorState
import com.tempcompany.tempapp.ui.components.LoadingState
import com.tempcompany.tempapp.ui.components.SectionCard
import com.tempcompany.tempapp.ui.components.StatusBadge
import com.tempcompany.tempapp.ui.nav.Routes
import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.formatCentsRange
import com.tempcompany.tempapp.util.relativeLabel
import com.tempcompany.tempapp.util.shortDate
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ClientDetailUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val client: ClientDto? = null,
    val estimates: List<EstimateDto> = emptyList(),
    // status editing
    val savingStatus: Boolean = false,
    val statusError: String? = null,
    // notes editing — text survives failures so nothing is re-entered
    val notesDraft: String = "",
    val notesSaved: String = "",
    val savingNotes: Boolean = false,
    val notesError: String? = null,
    val notesJustSaved: Boolean = false,
)

@HiltViewModel
class ClientDetailViewModel @Inject constructor(
    private val clientsRepository: ClientsRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(ClientDetailUiState())
    val state: StateFlow<ClientDetailUiState> = _state.asStateFlow()

    private var loadedId: Long = -1

    fun load(clientId: Long, force: Boolean = false) {
        if (loadedId == clientId && !force) return
        loadedId = clientId
        _state.value = ClientDetailUiState(loading = true)
        viewModelScope.launch {
            when (val r = clientsRepository.get(clientId)) {
                is ApiResult.Success -> _state.value = ClientDetailUiState(
                    loading = false,
                    client = r.data.client,
                    estimates = r.data.estimates,
                    notesDraft = r.data.client.notes ?: "",
                    notesSaved = r.data.client.notes ?: "",
                )
                is ApiResult.Error -> _state.value = ClientDetailUiState(loading = false, error = r.message)
            }
        }
    }

    fun setStatus(newStatus: String) {
        val s = _state.value
        val client = s.client ?: return
        if (s.savingStatus || newStatus == client.status) return
        _state.value = s.copy(savingStatus = true, statusError = null)
        viewModelScope.launch {
            when (val r = clientsRepository.update(client.id, status = newStatus)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    savingStatus = false,
                    client = _state.value.client?.copy(status = r.data.client.status),
                )
                is ApiResult.Error -> _state.value = _state.value.copy(
                    savingStatus = false,
                    statusError = r.message, // selection visually reverts (chips render from client.status)
                )
            }
        }
    }

    fun onNotesChange(text: String) {
        _state.value = _state.value.copy(notesDraft = text, notesJustSaved = false, notesError = null)
    }

    fun saveNotes() {
        val s = _state.value
        val client = s.client ?: return
        if (s.savingNotes) return
        _state.value = s.copy(savingNotes = true, notesError = null)
        viewModelScope.launch {
            when (val r = clientsRepository.update(client.id, notes = s.notesDraft)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    savingNotes = false,
                    notesSaved = r.data.client.notes ?: "",
                    notesJustSaved = true,
                )
                is ApiResult.Error -> _state.value = _state.value.copy(
                    savingNotes = false,
                    notesError = r.message, // draft text intact — retry without re-typing
                )
            }
        }
    }
}

/** Full client view: contact, pipeline status, notes, estimate history. */
@Composable
fun ClientDetailScreen(
    navController: NavController,
    clientId: Long,
    viewModel: ClientDetailViewModel = hiltViewModel(),
) {
    LaunchedEffect(clientId) { viewModel.load(clientId) }
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    when {
        state.loading -> LoadingState()
        state.error != null -> ErrorState(state.error!!, onRetry = { viewModel.load(clientId, force = true) })
        else -> {
            val client = state.client ?: return
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp)
                    .widthIn(max = 700.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                    Text(
                        client.name,
                        style = MaterialTheme.typography.headlineMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.weight(1f),
                    )
                    StatusBadge(client.status)
                }

                SectionCard(title = "Contact") {
                    DetailRow("Email", client.email) {
                        context.startActivity(Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:${client.email}")))
                    }
                    DetailRow("Phone", client.phone) {
                        context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${client.phone}")))
                    }
                    client.address?.takeIf { it.isNotBlank() }?.let { DetailRow("Address", it, null) }
                    DetailRow("Location", "${client.zipCode}, ${client.state}", null)
                    DetailRow("Consent to contact", if (client.consentTcpa == 1) "Yes" else "No", null)
                    DetailRow("Added", shortDate(client.createdAt), null)
                }

                SectionCard(title = "Pipeline status") {
                    Row(
                        modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        ApiEnums.CLIENT_STATUSES.forEach { status ->
                            FilterChip(
                                selected = client.status == status,
                                onClick = { viewModel.setStatus(status) },
                                enabled = !state.savingStatus,
                                label = { Text(status.replaceFirstChar { it.uppercase() }) },
                            )
                        }
                    }
                    if (state.savingStatus) {
                        Row(Modifier.padding(top = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                            CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.padding(end = 8.dp))
                            Text("Saving…", style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                    state.statusError?.let {
                        Text(
                            it,
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(top = 8.dp),
                        )
                    }
                }

                SectionCard(title = "Notes") {
                    OutlinedTextField(
                        value = state.notesDraft,
                        onValueChange = viewModel::onNotesChange,
                        minLines = 4,
                        modifier = Modifier.fillMaxWidth(),
                        placeholder = { Text("Job notes, callbacks, roof access details…") },
                    )
                    state.notesError?.let {
                        Text(
                            it,
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(top = 8.dp),
                        )
                    }
                    Row(Modifier.padding(top = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                        Button(
                            onClick = viewModel::saveNotes,
                            enabled = !state.savingNotes && state.notesDraft != state.notesSaved,
                        ) {
                            Text(if (state.savingNotes) "Saving…" else "Save notes")
                        }
                        if (state.notesJustSaved) {
                            Text(
                                "Saved ✓",
                                color = MaterialTheme.colorScheme.primary,
                                style = MaterialTheme.typography.bodyMedium,
                                modifier = Modifier.padding(start = 12.dp),
                            )
                        }
                    }
                }

                SectionCard(title = "Estimates (${state.estimates.size})") {
                    if (state.estimates.isEmpty()) {
                        Text(
                            "No estimates for this client yet.",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    } else {
                        state.estimates.forEachIndexed { i, e ->
                            if (i > 0) HorizontalDivider(Modifier.padding(vertical = 8.dp))
                            EstimateHistoryRow(e)
                        }
                    }
                }

                OutlinedButton(
                    onClick = { navController.navigate(Routes.createEstimate(client.id)) },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("New estimate for ${client.name.substringBefore(' ')}") }
            }
        }
    }
}

@Composable
private fun DetailRow(label: String, value: String, onClick: (() -> Unit)?) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)
            .padding(vertical = 4.dp),
    ) {
        Text(
            label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.weight(0.4f),
        )
        Text(
            value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.SemiBold,
            color = if (onClick != null) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.weight(0.6f),
        )
    }
}

@Composable
private fun EstimateHistoryRow(e: EstimateDto) {
    Column {
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
        Text(
            "${e.roofMaterialDesired.replace('_', ' ')} · ${e.roofSqftEstimate} sq ft roof · " +
                "${e.stories} ${if (e.stories == 1) "story" else "stories"} · pitch ${e.pitch} · tear-off ${e.tearOffNeeded}",
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
