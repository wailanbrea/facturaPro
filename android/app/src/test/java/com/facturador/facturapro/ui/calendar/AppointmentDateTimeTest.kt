package com.facturador.facturapro.ui.calendar

import java.time.LocalDateTime
import com.facturador.facturapro.domain.model.normalizeAppointmentDateTime
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

    @Test
    fun parseAppointmentDateTime_converts_api_utc_to_app_timezone() {
        assertEquals(
            LocalDateTime.of(2026, 9, 24, 11, 0),
            parseAppointmentDateTime("2026-09-24T15:00:00.000000Z"),
        )
    }

    @Test
    fun normalizeAppointmentDateTime_converts_calendar_api_value() {
        assertEquals(
            "2026-09-24T11:00",
            normalizeAppointmentDateTime("2026-09-24T15:00:00.000000Z"),
        )
    }
}
