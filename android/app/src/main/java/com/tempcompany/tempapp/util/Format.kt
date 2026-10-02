package com.tempcompany.tempapp.util

import java.text.NumberFormat
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.concurrent.TimeUnit

/**
 * Money: the API sends integer cents everywhere. Dollars exist only at display
 * time, rounded to whole dollars (estimates are ranges — cents are noise).
 */
fun formatCents(cents: Long): String =
    "$" + NumberFormat.getIntegerInstance(Locale.US).format(Math.round(cents / 100.0))

/** "$9,091 – $11,616" range shorthand used across screens. */
fun formatCentsRange(low: Long, high: Long): String = "${formatCents(low)} – ${formatCents(high)}"

/** The Phase 1 backend runs in America/New_York and sends "yyyy-MM-dd HH:mm:ss". */
private val SERVER_ZONE: ZoneId = ZoneId.of("America/New_York")
private val SERVER_FORMAT: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")
private val SERVER_DATE: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd")

fun parseServerDateTime(value: String): Instant =
    LocalDateTime.parse(value, SERVER_FORMAT).atZone(SERVER_ZONE).toInstant()

fun parseServerDate(value: String): LocalDate = LocalDate.parse(value, SERVER_DATE)

/** Compact relative label for activity feeds: "just now", "3h ago", "5d ago", else a date. */
fun relativeLabel(serverDateTime: String): String {
    val instant = try { parseServerDateTime(serverDateTime) } catch (_: Exception) { return serverDateTime }
    val deltaMs = System.currentTimeMillis() - instant.toEpochMilli()
    val minutes = TimeUnit.MILLISECONDS.toMinutes(deltaMs)
    return when {
        minutes < 1 -> "just now"
        minutes < 60 -> "${minutes}m ago"
        minutes < 60 * 24 -> "${TimeUnit.MILLISECONDS.toHours(deltaMs)}h ago"
        minutes < 60 * 24 * 7 -> "${TimeUnit.MILLISECONDS.toDays(deltaMs)}d ago"
        else -> DateTimeFormatter.ofPattern("MMM d, yyyy")
            .withZone(SERVER_ZONE)
            .format(instant)
    }
}

/** "Jul 5, 2026" for detail screens. */
fun shortDate(serverDateTime: String): String = try {
    DateTimeFormatter.ofPattern("MMM d, yyyy").withZone(SERVER_ZONE).format(parseServerDateTime(serverDateTime))
} catch (_: Exception) {
    serverDateTime
}
