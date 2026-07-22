package com.facturador.facturapro.ui.calendar

import java.time.LocalDateTime
import org.junit.Assert.assertEquals
import org.junit.Test

class AppointmentDateTimeTest {

    @Test
    fun normalizedAppointmentEnd_moves_equal_end_time_one_hour_forward() {
        val start = LocalDateTime.of(2026, 7, 20, 10, 0)

        assertEquals(start.plusHours(1), normalizedAppointmentEnd(start, start))
    }

    @Test
    fun normalizedAppointmentEnd_preserves_valid_end_time() {
        val start = LocalDateTime.of(2026, 7, 20, 10, 0)
        val end = start.plusHours(2)

        assertEquals(end, normalizedAppointmentEnd(start, end))
    }
}
