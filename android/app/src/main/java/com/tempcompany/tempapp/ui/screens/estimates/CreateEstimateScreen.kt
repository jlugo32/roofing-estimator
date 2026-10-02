package com.tempcompany.tempapp.ui.screens.estimates

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import com.tempcompany.tempapp.data.api.ApiEnums
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.ClientDto
import com.tempcompany.tempapp.data.api.EstimateCreateData
import com.tempcompany.tempapp.data.api.EstimateCreateRequest
import com.tempcompany.tempapp.data.api.NewClientBody
import com.tempcompany.tempapp.data.repo.ClientsRepository
import com.tempcompany.tempapp.data.repo.CompanyRepository
import com.tempcompany.tempapp.data.repo.DraftStore
import com.tempcompany.tempapp.data.repo.EstimatesRepository
import com.tempcompany.tempapp.ui.components.SectionCard
import com.tempcompany.tempapp.ui.components.StatusBadge
import com.tempcompany.tempapp.ui.nav.Routes
import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.formatCentsRange
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

private val US_STATES = listOf(
    "AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "DC", "FL", "GA", "HI", "ID", "IL", "IN",
    "IA", "KS", "KY", "LA", "ME", "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH",
    "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC", "SD", "TN", "TX", "UT",
    "VT", "VA", "WA", "WV", "WI", "WY",
)

/** Contract-fixed material keys; live labels come from company/profile.php when reachable. */
private val FALLBACK_MATERIALS = mapOf(
    "asphalt_3tab" to "Asphalt Shingles (3-Tab)",
    "asphalt_architectural" to "Asphalt Shingles (Architectural)",
    "metal" to "Metal Roofing",
    "tile" to "Tile Roofing",
    "flat_tpo" to "Flat / TPO Membrane",
    "slate" to "Slate Roofing",
)

enum class ClientMode { EXISTING, NEW }

data class CreateEstimateUiState(
    val step: Int = 1, // 1 Client, 2 Home, 3 Roof, 4 Review
    val materials: Map<String, String> = FALLBACK_MATERIALS,
    // step 1
    val clientMode: ClientMode = ClientMode.EXISTING,
    val search: String = "",
    val searching: Boolean = false,
    val searchResults: List<ClientDto> = emptyList(),
    val selectedClientId: Long? = null,
    val selectedClientName: String? = null,
    val newName: String = "",
    val newEmail: String = "",
    val newPhone: String = "",
    val newZip: String = "",
    val newState: String = "",
    val newAddress: String = "",
    val newConsent: Boolean = false,
    // step 2
    val homeSqft: String = "",
    val stories: Int = 1,
    // step 3
    val materialCurrent: String = "unknown",
    val materialDesired: String = "",
    val roofAge: String = "",
    val condition: String = "unknown",
    val pitch: String = "unsure",
    val tearOff: String = "unsure",
    // step 4
    val sendEmail: Boolean = false,
    val validationError: String? = null,
    val submitting: Boolean = false,
    val submitError: String? = null,
    val result: EstimateCreateData? = null,
    // crash/offline recovery
    val pendingDraft: EstimateCreateRequest? = null,
)

@HiltViewModel
class CreateEstimateViewModel @Inject constructor(
    private val estimatesRepository: EstimatesRepository,
    private val clientsRepository: ClientsRepository,
    private val companyRepository: CompanyRepository,
    private val draftStore: DraftStore,
) : ViewModel() {

    private val _state = MutableStateFlow(CreateEstimateUiState())
    val state: StateFlow<CreateEstimateUiState> = _state.asStateFlow()

    private var searchJob: Job? = null
    private var preselected = false

    init {
        viewModelScope.launch {
            when (val r = companyRepository.profile()) {
                is ApiResult.Success -> _state.value = _state.value.copy(materials = r.data.materials)
                is ApiResult.Error -> Unit // fallback labels already in place
            }
        }
        viewModelScope.launch {
            draftStore.pendingEstimateDraft()?.let { draft ->
                _state.value = _state.value.copy(pendingDraft = draft)
            }
        }
    }

    fun preselectClient(clientId: Long) {
        if (preselected) return
        preselected = true
        viewModelScope.launch {
            when (val r = clientsRepository.get(clientId)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    clientMode = ClientMode.EXISTING,
                    selectedClientId = r.data.client.id,
                    selectedClientName = r.data.client.name,
                )
                is ApiResult.Error -> Unit // rep can still search manually
            }
        }
    }

    fun update(transform: (CreateEstimateUiState) -> CreateEstimateUiState) {
        _state.value = transform(_state.value).copy(validationError = null)
    }

    fun onSearch(text: String) {
        _state.value = _state.value.copy(search = text)
        searchJob?.cancel()
        if (text.isBlank()) {
            _state.value = _state.value.copy(searchResults = emptyList(), searching = false)
            return
        }
        searchJob = viewModelScope.launch {
            delay(400)
            _state.value = _state.value.copy(searching = true)
            when (val r = clientsRepository.list(page = 1, perPage = 10, query = text)) {
                is ApiResult.Success -> _state.value = _state.value.copy(
                    searching = false, searchResults = r.data.items,
                )
                is ApiResult.Error -> _state.value = _state.value.copy(searching = false)
            }
        }
    }

    fun next() {
        val error = validate(_state.value.step)
        if (error != null) {
            _state.value = _state.value.copy(validationError = error)
        } else {
            _state.value = _state.value.copy(step = (_state.value.step + 1).coerceAtMost(4))
        }
    }

    fun back() {
        _state.value = _state.value.copy(step = (_state.value.step - 1).coerceAtLeast(1), validationError = null)
    }

    private fun validate(step: Int): String? {
        val s = _state.value
        return when (step) {
            1 -> when (s.clientMode) {
                ClientMode.EXISTING ->
                    if (s.selectedClientId == null) "Select a client, or switch to \"New client\"." else null
                ClientMode.NEW -> when {
                    s.newName.isBlank() -> "Homeowner name is required."
                    !s.newEmail.contains('@') || s.newEmail.length < 5 -> "A valid email is required."
                    s.newPhone.filter { it.isDigit() }.length < 10 -> "A valid phone number is required (10+ digits)."
                    !s.newZip.matches(Regex("""\d{5}(-\d{4})?""")) -> "A valid 5-digit ZIP is required."
                    s.newState.length != 2 -> "Select the state."
                    else -> null
                }
            }
            2 -> {
                val sqft = s.homeSqft.toIntOrNull()
                if (sqft == null || sqft < 200 || sqft > 20000) "Home size must be between 200 and 20,000 sq ft." else null
            }
            3 -> if (s.materialDesired.isBlank()) "Choose the desired roof material." else null
            else -> null
        }
    }

    private fun buildRequest(): EstimateCreateRequest {
        val s = _state.value
        return EstimateCreateRequest(
            clientId = if (s.clientMode == ClientMode.EXISTING) s.selectedClientId else null,
            client = if (s.clientMode == ClientMode.NEW) NewClientBody(
                name = s.newName.trim(),
                email = s.newEmail.trim(),
                phone = s.newPhone.trim(),
                zipCode = s.newZip.trim(),
                state = s.newState,
                address = s.newAddress.trim().ifBlank { null },
                consentTcpa = if (s.newConsent) 1 else 0,
            ) else null,
            homeSqft = s.homeSqft.toIntOrNull() ?: 0,
            stories = s.stories,
            roofMaterialCurrent = s.materialCurrent,
            roofMaterialDesired = s.materialDesired,
            roofAgeYears = s.roofAge.toIntOrNull(),
            roofCondition = s.condition,
            pitch = s.pitch,
            tearOff = s.tearOff,
            sendEmail = if (s.sendEmail) true else null,
        )
    }

    /** Spec rule: persist BEFORE sending — a failed submit is retryable forever. */
    fun submit() {
        if (_state.value.submitting) return
        _state.value = _state.value.copy(submitting = true, submitError = null)
        viewModelScope.launch {
            val request = buildRequest()
            draftStore.saveEstimateDraft(request)
            when (val r = estimatesRepository.create(request)) {
                is ApiResult.Success -> {
                    draftStore.clearEstimateDraft()
                    _state.value = _state.value.copy(submitting = false, result = r.data, pendingDraft = null)
                }
                is ApiResult.Error -> _state.value = _state.value.copy(
                    submitting = false,
                    submitError = r.message, // form state + persisted draft intact
                )
            }
        }
    }

    /** Recovery banner: submit the draft persisted by a previous, failed attempt. */
    fun submitPendingDraft() {
        val draft = _state.value.pendingDraft ?: return
        if (_state.value.submitting) return
        _state.value = _state.value.copy(submitting = true, submitError = null)
        viewModelScope.launch {
            when (val r = estimatesRepository.create(draft)) {
                is ApiResult.Success -> {
                    draftStore.clearEstimateDraft()
                    _state.value = _state.value.copy(submitting = false, result = r.data, pendingDraft = null)
                }
                is ApiResult.Error -> _state.value = _state.value.copy(
                    submitting = false, submitError = r.message,
                )
            }
        }
    }

    fun discardPendingDraft() {
        viewModelScope.launch {
            draftStore.clearEstimateDraft()
            _state.value = _state.value.copy(pendingDraft = null)
        }
    }
}

/** 4-step on-site estimate wizard (client → home → roof → review). */
@Composable
fun CreateEstimateScreen(
    navController: NavController,
    preselectedClientId: Long?,
    viewModel: CreateEstimateViewModel = hiltViewModel(),
) {
    LaunchedEffect(preselectedClientId) {
        preselectedClientId?.let { viewModel.preselectClient(it) }
    }
    val state by viewModel.state.collectAsState()

    // Success panel replaces the wizard entirely.
    state.result?.let { result ->
        ResultPanel(
            result = result,
            onDone = { navController.popBackStack() },
            onViewClient = { navController.navigate(Routes.clientDetail(result.clientId)) },
        )
        return
    }

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
                "New estimate",
                style = MaterialTheme.typography.headlineMedium,
                color = MaterialTheme.colorScheme.primary,
            )
        }

        StepIndicator(current = state.step)

        if (state.pendingDraft != null) {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer)) {
                Column(Modifier.padding(14.dp)) {
                    Text(
                        "You have an unsent estimate from a previous attempt",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSecondaryContainer,
                    )
                    Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(onClick = viewModel::submitPendingDraft, enabled = !state.submitting) {
                            Text(if (state.submitting) "Sending…" else "Submit now")
                        }
                        OutlinedButton(onClick = viewModel::discardPendingDraft, enabled = !state.submitting) {
                            Text("Discard")
                        }
                    }
                }
            }
        }

        when (state.step) {
            1 -> StepClient(state, viewModel)
            2 -> StepHome(state, viewModel)
            3 -> StepRoof(state, viewModel)
            4 -> StepReview(state, viewModel)
        }

        state.validationError?.let {
            Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
        }
        state.submitError?.let {
            Text(
                "Couldn't save the estimate: $it",
                color = MaterialTheme.colorScheme.error,
                style = MaterialTheme.typography.bodyMedium,
            )
        }

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            if (state.step > 1) {
                OutlinedButton(onClick = viewModel::back, modifier = Modifier.weight(1f)) { Text("Back") }
            }
            if (state.step < 4) {
                Button(onClick = viewModel::next, modifier = Modifier.weight(2f)) { Text("Continue") }
            } else {
                Button(
                    onClick = viewModel::submit,
                    enabled = !state.submitting,
                    modifier = Modifier.weight(2f),
                ) {
                    if (state.submitting) {
                        CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                        Text("  Calculating…")
                    } else {
                        Text(if (state.submitError != null) "Retry" else "Create estimate")
                    }
                }
            }
        }
    }
}

@Composable
private fun StepIndicator(current: Int) {
    val labels = listOf("Client", "Home", "Roof", "Review")
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        labels.forEachIndexed { index, label ->
            val stepNo = index + 1
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Box(
                    modifier = Modifier
                        .size(32.dp)
                        .background(
                            when {
                                stepNo == current -> MaterialTheme.colorScheme.secondary
                                stepNo < current -> MaterialTheme.colorScheme.primary
                                else -> MaterialTheme.colorScheme.surfaceVariant
                            },
                            CircleShape,
                        ),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        stepNo.toString(),
                        color = if (stepNo <= current) MaterialTheme.colorScheme.onPrimary
                        else MaterialTheme.colorScheme.onSurfaceVariant,
                        style = MaterialTheme.typography.labelLarge,
                    )
                }
                Text(label, style = MaterialTheme.typography.labelSmall)
            }
        }
    }
}

@Composable
private fun StepClient(state: CreateEstimateUiState, viewModel: CreateEstimateViewModel) {
    SectionCard(title = "Who is this estimate for?") {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(
                selected = state.clientMode == ClientMode.EXISTING,
                onClick = { viewModel.update { it.copy(clientMode = ClientMode.EXISTING) } },
                label = { Text("Existing client") },
            )
            FilterChip(
                selected = state.clientMode == ClientMode.NEW,
                onClick = { viewModel.update { it.copy(clientMode = ClientMode.NEW) } },
                label = { Text("New client") },
            )
        }

        if (state.clientMode == ClientMode.EXISTING) {
            if (state.selectedClientId != null) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                    modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
                ) {
                    Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            state.selectedClientName ?: "Client #${state.selectedClientId}",
                            style = MaterialTheme.typography.titleMedium,
                            modifier = Modifier.weight(1f),
                        )
                        OutlinedButton(onClick = {
                            viewModel.update { it.copy(selectedClientId = null, selectedClientName = null) }
                        }) { Text("Change") }
                    }
                }
            } else {
                OutlinedTextField(
                    value = state.search,
                    onValueChange = viewModel::onSearch,
                    placeholder = { Text("Search clients by name, email, phone") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
                )
                if (state.searching) {
                    CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.padding(top = 8.dp).size(18.dp))
                }
                state.searchResults.forEach { client ->
                    HorizontalDivider(Modifier.padding(vertical = 6.dp))
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                viewModel.update {
                                    it.copy(selectedClientId = client.id, selectedClientName = client.name)
                                }
                            },
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column(Modifier.weight(1f)) {
                            Text(client.name, style = MaterialTheme.typography.titleMedium)
                            Text(
                                client.email,
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        StatusBadge(client.status)
                    }
                }
            }
        } else {
            OutlinedTextField(
                value = state.newName,
                onValueChange = { v -> viewModel.update { it.copy(newName = v) } },
                label = { Text("Homeowner name") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
            )
            OutlinedTextField(
                value = state.newEmail,
                onValueChange = { v -> viewModel.update { it.copy(newEmail = v) } },
                label = { Text("Email") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
            )
            OutlinedTextField(
                value = state.newPhone,
                onValueChange = { v -> viewModel.update { it.copy(newPhone = v) } },
                label = { Text("Phone") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 8.dp)) {
                OutlinedTextField(
                    value = state.newZip,
                    onValueChange = { v -> viewModel.update { it.copy(newZip = v) } },
                    label = { Text("ZIP") },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.weight(1f),
                )
                StateDropdown(
                    value = state.newState,
                    onSelect = { v -> viewModel.update { it.copy(newState = v) } },
                    modifier = Modifier.weight(1f),
                )
            }
            OutlinedTextField(
                value = state.newAddress,
                onValueChange = { v -> viewModel.update { it.copy(newAddress = v) } },
                label = { Text("Street address (optional)") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
            )
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
            ) {
                Switch(
                    checked = state.newConsent,
                    onCheckedChange = { v -> viewModel.update { it.copy(newConsent = v) } },
                )
                Text(
                    "Homeowner agrees to be contacted about this project",
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(start = 8.dp),
                )
            }
        }
    }
}

@Composable
private fun StateDropdown(value: String, onSelect: (String) -> Unit, modifier: Modifier = Modifier) {
    var open by remember { mutableStateOf(false) }
    Box(modifier) {
        OutlinedButton(onClick = { open = true }, modifier = Modifier.fillMaxWidth()) {
            Text(value.ifBlank { "State" })
            Icon(Icons.Filled.ArrowDropDown, contentDescription = null)
        }
        DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            // Plain menu is fine for 51 short items.
            US_STATES.forEach { code ->
                DropdownMenuItem(text = { Text(code) }, onClick = { open = false; onSelect(code) })
            }
        }
    }
}

@Composable
private fun StepHome(state: CreateEstimateUiState, viewModel: CreateEstimateViewModel) {
    SectionCard(title = "Home details") {
        OutlinedTextField(
            value = state.homeSqft,
            onValueChange = { v -> viewModel.update { it.copy(homeSqft = v.filter { c -> c.isDigit() }) } },
            label = { Text("Home square footage") },
            supportingText = { Text("Total finished living area (200–20,000)") },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            modifier = Modifier.fillMaxWidth(),
        )
        Text("Stories", style = MaterialTheme.typography.labelLarge, modifier = Modifier.padding(top = 12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            listOf(1 to "1", 2 to "2", 3 to "3+").forEach { (n, label) ->
                FilterChip(
                    selected = state.stories == n,
                    onClick = { viewModel.update { it.copy(stories = n) } },
                    label = { Text(label) },
                )
            }
        }
    }
}

@Composable
private fun ChipGroup(
    title: String,
    options: List<Pair<String, String>>, // value → label
    selected: String,
    onSelect: (String) -> Unit,
) {
    Text(title, style = MaterialTheme.typography.labelLarge, modifier = Modifier.padding(top = 12.dp))
    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(top = 4.dp),
    ) {
        options.forEach { (value, label) ->
            FilterChip(selected = selected == value, onClick = { onSelect(value) }, label = { Text(label) })
        }
    }
}

@Composable
private fun StepRoof(state: CreateEstimateUiState, viewModel: CreateEstimateViewModel) {
    SectionCard(title = "Roof details") {
        ChipGroup(
            title = "Current material",
            options = listOf("unknown" to "Not sure") + state.materials.map { it.key to it.value },
            selected = state.materialCurrent,
            onSelect = { v -> viewModel.update { it.copy(materialCurrent = v) } },
        )
        ChipGroup(
            title = "Desired material *",
            options = state.materials.map { it.key to it.value },
            selected = state.materialDesired,
            onSelect = { v -> viewModel.update { it.copy(materialDesired = v) } },
        )
        OutlinedTextField(
            value = state.roofAge,
            onValueChange = { v -> viewModel.update { it.copy(roofAge = v.filter { c -> c.isDigit() }) } },
            label = { Text("Roof age in years (optional)") },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            modifier = Modifier.fillMaxWidth().padding(top = 12.dp),
        )
        ChipGroup(
            title = "Condition",
            options = ApiEnums.ROOF_CONDITIONS.map { it to if (it == "unknown") "Not sure" else it.replaceFirstChar { c -> c.uppercase() } },
            selected = state.condition,
            onSelect = { v -> viewModel.update { it.copy(condition = v) } },
        )
        ChipGroup(
            title = "Pitch",
            options = ApiEnums.PITCHES.map { it to if (it == "unsure") "Not sure" else it.replaceFirstChar { c -> c.uppercase() } },
            selected = state.pitch,
            onSelect = { v -> viewModel.update { it.copy(pitch = v) } },
        )
        ChipGroup(
            title = "Tear-off needed",
            options = ApiEnums.TEAR_OFF.map { it to if (it == "unsure") "Not sure" else it.replaceFirstChar { c -> c.uppercase() } },
            selected = state.tearOff,
            onSelect = { v -> viewModel.update { it.copy(tearOff = v) } },
        )
    }
}

@Composable
private fun StepReview(state: CreateEstimateUiState, viewModel: CreateEstimateViewModel) {
    SectionCard(title = "Review") {
        ReviewRow(
            "Client",
            if (state.clientMode == ClientMode.EXISTING) state.selectedClientName ?: "—"
            else "${state.newName} (new)",
        )
        ReviewRow("Home", "${state.homeSqft} sq ft · ${state.stories} ${if (state.stories == 1) "story" else "stories"}")
        ReviewRow("Desired material", state.materials[state.materialDesired] ?: state.materialDesired)
        ReviewRow("Current material", if (state.materialCurrent == "unknown") "Not sure" else state.materials[state.materialCurrent] ?: state.materialCurrent)
        if (state.roofAge.isNotBlank()) ReviewRow("Roof age", "${state.roofAge} years")
        ReviewRow("Condition / pitch / tear-off", "${state.condition} / ${state.pitch} / ${state.tearOff}")
        HorizontalDivider(Modifier.padding(vertical = 10.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Switch(
                checked = state.sendEmail,
                onCheckedChange = { v -> viewModel.update { it.copy(sendEmail = v) } },
            )
            Text(
                "Email the estimate to the homeowner",
                style = MaterialTheme.typography.bodyMedium,
                modifier = Modifier.padding(start = 8.dp),
            )
        }
    }
}

@Composable
private fun ReviewRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
        Text(
            label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.weight(0.45f),
        )
        Text(value, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(0.55f))
    }
}

@Composable
private fun ResultPanel(result: EstimateCreateData, onDone: () -> Unit, onViewClient: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(24.dp)
            .widthIn(max = 600.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text("Estimate ready", style = MaterialTheme.typography.headlineMedium, color = MaterialTheme.colorScheme.primary)
        Text(
            formatCentsRange(result.estimateLowCents, result.estimateHighCents),
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.secondary,
            modifier = Modifier.padding(top = 12.dp),
        )
        Text(
            "Roof estimate: ${result.roofSqftEstimate} sq ft",
            style = MaterialTheme.typography.bodyLarge,
            modifier = Modifier.padding(top = 8.dp),
        )
        result.financingMonthlyCents?.takeIf { it > 0 }?.let {
            Text(
                "from ${formatCents(it)}/mo with financing",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Text(
            "This preliminary range is saved to the client's history. A final quote follows the on-site inspection.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(top = 16.dp),
        )
        Button(onClick = onViewClient, modifier = Modifier.fillMaxWidth().padding(top = 20.dp)) { Text("View client") }
        OutlinedButton(onClick = onDone, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) { Text("Done") }
    }
}
