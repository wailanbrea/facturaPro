package com.facturador.facturapro.ui.calendar

import com.facturador.facturapro.domain.model.Appointment
import java.time.YearMonth

data class CalendarUiState(
    val yearMonth: YearMonth = YearMonth.now(),
    val appointments: List<Appointment> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val selectedDate: String? = null,
)
