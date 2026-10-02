package com.tempcompany.tempapp.ui.screens.company

import android.content.Intent
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material3.Button
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.navigation.NavController
import coil.compose.AsyncImage
import com.tempcompany.tempapp.BuildConfig
import com.tempcompany.tempapp.data.api.ApiResult
import com.tempcompany.tempapp.data.api.CompanyProfileData
import com.tempcompany.tempapp.data.repo.CompanyRepository
import com.tempcompany.tempapp.ui.components.ErrorState
import com.tempcompany.tempapp.ui.components.LoadingState
import com.tempcompany.tempapp.ui.components.SectionCard
import com.tempcompany.tempapp.ui.theme.BadgeContactedBg
import com.tempcompany.tempapp.ui.theme.BadgeContactedFg
import com.tempcompany.tempapp.ui.theme.BadgeLostBg
import com.tempcompany.tempapp.ui.theme.BadgeLostFg
import com.tempcompany.tempapp.ui.theme.BadgeWonBg
import com.tempcompany.tempapp.ui.theme.BadgeWonFg
import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.parseServerDate
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.format.DateTimeFormatter
import javax.inject.Inject

data class CompanyUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val profile: CompanyProfileData? = null,
)

@HiltViewModel
class CompanyViewModel @Inject constructor(
    private val companyRepository: CompanyRepository,
) : ViewModel() {

    private val _state = MutableStateFlow(CompanyUiState())
    val state: StateFlow<CompanyUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            when (val r = companyRepository.profile()) {
                is ApiResult.Success -> _state.value = CompanyUiState(loading = false, profile = r.data)
                is ApiResult.Error -> _state.value = _state.value.copy(loading = false, error = r.message)
            }
        }
    }
}

/** Read-only company profile + pricing (editing stays on the web dashboard, per spec). */
@Composable
fun CompanyScreen(
    navController: NavController,
    viewModel: CompanyViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    when {
        state.loading -> LoadingState()
        state.error != null -> ErrorState(state.error!!, onRetry = viewModel::load)
        else -> {
            val profile = state.profile ?: return
            val company = profile.company
            val context = LocalContext.current
            var multipliersOpen by remember { mutableStateOf(false) }

            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp)
                    .widthIn(max = 700.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                SectionCard {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        if (company.logoPath != null) {
                            AsyncImage(
                                model = BuildConfig.API_BASE_URL.removeSuffix("api/v1/") +
                                    company.logoPath!!.trimStart('/'),
                                contentDescription = "${company.name} logo",
                                modifier = Modifier.size(56.dp).padding(end = 12.dp),
                            )
                        }
                        Column(Modifier.weight(1f)) {
                            Text(
                                company.name,
                                style = MaterialTheme.typography.headlineMedium,
                                color = MaterialTheme.colorScheme.primary,
                            )
                            Text(
                                "/${company.slug}",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        SubscriptionChip(company.subscriptionStatus)
                    }
                    HorizontalDivider(Modifier.padding(vertical = 12.dp))
                    ProfileRow("Email", company.contactEmail)
                    ProfileRow("Phone", company.contactPhone)
                    if (company.serviceArea.isNotBlank()) ProfileRow("Service area", company.serviceArea)
                    company.licenseNumber?.let { ProfileRow("License", it) }
                    if (company.subscriptionStatus == "trial" && company.trialEndsAt != null) {
                        val ends = runCatching {
                            DateTimeFormatter.ofPattern("MMM d, yyyy").format(parseServerDate(company.trialEndsAt!!))
                        }.getOrDefault(company.trialEndsAt!!)
                        ProfileRow("Trial ends", ends)
                    }
                    Text(
                        "Edit company details in the web dashboard.",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 8.dp),
                    )
                }

                SectionCard(title = "Your public estimator") {
                    Text(company.estimatorUrl, style = MaterialTheme.typography.bodyMedium)
                    Button(
                        onClick = {
                            val send = Intent(Intent.ACTION_SEND).apply {
                                type = "text/plain"
                                putExtra(Intent.EXTRA_TEXT, company.estimatorUrl)
                            }
                            context.startActivity(Intent.createChooser(send, "Share estimator link"))
                        },
                        modifier = Modifier.padding(top = 8.dp),
                    ) { Text("Share link") }
                }

                SectionCard(title = "Pricing — cost per square") {
                    val costs = profile.pricingSettings
                        .filterKeys { it.startsWith("cost_per_square_") && it.endsWith("_cents") }
                        .toSortedMap()
                    costs.forEach { (key, value) ->
                        val materialKey = key.removePrefix("cost_per_square_").removeSuffix("_cents")
                        ProfileRow(
                            profile.materials[materialKey] ?: materialKey.replace('_', ' '),
                            formatCents(value.toLongOrNull() ?: 0L),
                        )
                    }
                    Text(
                        "Edit pricing in the web dashboard — changes apply to the estimator instantly.",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 8.dp),
                    )
                }

                SectionCard(title = "Fees, surcharges & financing") {
                    val p = profile.pricingSettings
                    ProfileRow("Tear-off flat fee", formatCents(p["tear_off_flat_fee_cents"]?.toLongOrNull() ?: 0L))
                    ProfileRow("Permit flat fee", formatCents(p["permit_fee_flat_cents"]?.toLongOrNull() ?: 0L))
                    ProfileRow("Steep pitch surcharge", "${p["steep_pitch_surcharge_pct"] ?: "0"}%")
                    ProfileRow("Multi-story surcharge", "${p["multi_story_surcharge_pct"] ?: "0"}%")
                    ProfileRow("Waste factor", "${p["waste_factor_pct"] ?: "0"}%")
                    ProfileRow("Financing APR", "${p["financing_default_apr"] ?: "0"}%")
                    ProfileRow("Financing term", "${p["financing_default_term_months"] ?: "0"} months")
                }

                SectionCard {
                    Row(
                        modifier = Modifier.fillMaxWidth().clickable { multipliersOpen = !multipliersOpen },
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(
                            "Regional multipliers (51 states)",
                            style = MaterialTheme.typography.titleMedium,
                            modifier = Modifier.weight(1f),
                        )
                        Icon(
                            if (multipliersOpen) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                            contentDescription = if (multipliersOpen) "Collapse" else "Expand",
                        )
                    }
                    if (multipliersOpen) {
                        val multipliers = profile.pricingSettings
                            .filterKeys { it.startsWith("regional_multiplier_") }
                            .toSortedMap()
                        multipliers.forEach { (key, value) ->
                            ProfileRow(key.removePrefix("regional_multiplier_"), "×$value")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ProfileRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
        Text(
            label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.weight(1f),
        )
        Text(value, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
    }
}

/** Subscription status chip; tones follow the web .badge-* palette. */
@Composable
private fun SubscriptionChip(status: String) {
    val (bg, fg) = when (status) {
        "active" -> BadgeWonBg to BadgeWonFg
        "trial" -> BadgeContactedBg to BadgeContactedFg
        "past_due", "suspended" -> Color(0xFFFEE2E2) to Color(0xFFB91C1C)
        else -> BadgeLostBg to BadgeLostFg
    }
    Box(Modifier.background(bg, RoundedCornerShape(999.dp)).padding(horizontal = 10.dp, vertical = 3.dp)) {
        Text(
            status.replace('_', ' ').replaceFirstChar { it.uppercase() },
            style = MaterialTheme.typography.labelSmall,
            color = fg,
            fontWeight = FontWeight.SemiBold,
        )
    }
}
