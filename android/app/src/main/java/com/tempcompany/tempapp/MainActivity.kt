package com.tempcompany.tempapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.material3.windowsizeclass.ExperimentalMaterial3WindowSizeClassApi
import androidx.compose.material3.windowsizeclass.calculateWindowSizeClass
import com.tempcompany.tempapp.ui.nav.AppRoot
import com.tempcompany.tempapp.ui.theme.AppTheme
import dagger.hilt.android.AndroidEntryPoint

/** Single-activity architecture — all screens are Compose destinations under AppRoot. */
@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @OptIn(ExperimentalMaterial3WindowSizeClassApi::class)
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            AppTheme {
                // Phone vs tablet adaptivity flows from this one measurement.
                AppRoot(windowSizeClass = calculateWindowSizeClass(this))
            }
        }
    }
}
