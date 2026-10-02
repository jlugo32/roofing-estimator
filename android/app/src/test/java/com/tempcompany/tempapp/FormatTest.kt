package com.tempcompany.tempapp

import com.tempcompany.tempapp.util.formatCents
import com.tempcompany.tempapp.util.formatCentsRange
import com.tempcompany.tempapp.util.parseServerDateTime
import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.ZoneId

class FormatTest {

    @Test
    fun `cents format to whole dollars`() {
        assertEquals("$0", formatCents(0))
        assertEquals("$1", formatCents(99)) // rounds
        assertEquals("$9,091", formatCents(909_079))
        assertEquals("$24,417", formatCents(2_441_738)) // 24417.38 rounds down
    }

    @Test
    fun `range format`() {
        assertEquals("$9,091 – $11,616", formatCentsRange(909_079, 1_161_601))
    }

    @Test
    fun `server datetime parses in backend timezone`() {
        val instant = parseServerDateTime("2026-07-05 09:02:11")
        val ny = instant.atZone(ZoneId.of("America/New_York"))
        assertEquals(9, ny.hour)
        assertEquals(5, ny.dayOfMonth)
    }
}
