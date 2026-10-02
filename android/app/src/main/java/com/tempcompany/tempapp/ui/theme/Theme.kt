package com.tempcompany.tempapp.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

/**
 * Brand palette in light AND dark, mirroring the web app. We deliberately do NOT
 * use Android 12 dynamic color: the whole point of the shared Color.kt is that
 * web and app read as one product, and dynamic color would repaint the brand
 * per-device. Dark mode follows the system setting.
 */

private val LightColors = lightColorScheme(
    primary = Primary,
    onPrimary = OnPrimary,
    primaryContainer = PrimaryContainer,
    onPrimaryContainer = OnPrimaryContainer,
    secondary = Accent,
    onSecondary = OnAccent,
    secondaryContainer = AccentContainer,
    onSecondaryContainer = OnAccentContainer,
    tertiary = PrimaryDark,
    background = SurfaceAltLight,
    onBackground = Ink,
    surface = SurfaceLight,
    onSurface = Ink,
    surfaceVariant = SurfaceAltLight,
    onSurfaceVariant = Muted,
    error = Danger,
    onError = OnPrimary,
)

private val DarkColors = darkColorScheme(
    primary = PrimaryLightOnDark,
    onPrimary = PrimaryDark,
    primaryContainer = PrimaryDark,
    onPrimaryContainer = PrimaryContainer,
    secondary = AccentLightOnDark,
    onSecondary = AccentDark,
    secondaryContainer = AccentDark,
    onSecondaryContainer = AccentContainer,
    tertiary = PrimaryLightOnDark,
    background = SurfaceDark,
    onBackground = InkOnDark,
    surface = SurfaceAltDark,
    onSurface = InkOnDark,
    surfaceVariant = SurfaceAltDark,
    onSurfaceVariant = MutedOnDark,
    error = DangerOnDark,
    onError = SurfaceDark,
)

@Composable
fun AppTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = AppTypography,
        content = content,
    )
}
