package com.tempcompany.tempapp.ui.theme

import androidx.compose.ui.graphics.Color

/**
 * Single source of truth for brand color, mirroring the web app's
 * assets/css/main.css custom properties so both feel like one product.
 */

// --color-primary / --color-primary-dark (deep slate blue — trust/structure)
val Primary = Color(0xFF1E3A5F)
val PrimaryDark = Color(0xFF162C49)
val OnPrimary = Color(0xFFFFFFFF)
val PrimaryContainer = Color(0xFFD3E2F5)
val OnPrimaryContainer = Color(0xFF0B1F38)

// --color-accent (roofing terracotta — CTAs)
val Accent = Color(0xFFC2410C)
val AccentDark = Color(0xFF9A3412)
val OnAccent = Color(0xFFFFFFFF)
val AccentContainer = Color(0xFFFFDBCC)
val OnAccentContainer = Color(0xFF3B1000)

// Ink / surfaces
val Ink = Color(0xFF1F2937)
val Muted = Color(0xFF6B7280)
val SurfaceLight = Color(0xFFFFFFFF)
val SurfaceAltLight = Color(0xFFF4F5F7)

// Status
val Success = Color(0xFF15803D)
val Danger = Color(0xFFB91C1C)
val Warning = Color(0xFFB45309)

// Dark theme partners
val PrimaryLightOnDark = Color(0xFFA6C4EA)
val AccentLightOnDark = Color(0xFFFFB596)
val SurfaceDark = Color(0xFF121821)
val SurfaceAltDark = Color(0xFF1B2430)
val InkOnDark = Color(0xFFE3E6EB)

val MutedOnDark = Color(0xFF9AA3AF)
val DangerOnDark = Color(0xFFF2B8B5)

// Client pipeline badge colors (match web .badge-* classes)
val BadgeNewBg = Color(0xFFDBEAFE);       val BadgeNewFg = Color(0xFF1D4ED8)
val BadgeContactedBg = Color(0xFFFEF3C7); val BadgeContactedFg = Color(0xFFB45309)
val BadgeQuotedBg = Color(0xFFEDE9FE);    val BadgeQuotedFg = Color(0xFF6D28D9)
val BadgeWonBg = Color(0xFFDCFCE7);       val BadgeWonFg = Color(0xFF15803D)
val BadgeLostBg = Color(0xFFF3F4F6);      val BadgeLostFg = Color(0xFF6B7280)
