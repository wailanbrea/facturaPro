package com.facturador.facturapro.ui.calendar

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.data.repository.CalendarRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.YearMonth

class CalendarViewModel(private val repository: CalendarRepository) : ViewModel() {

    private val _state = MutableStateFlow(CalendarUiState())
    val state: StateFlow<CalendarUiState> = _state.asStateFlow()

    init {
        loadMonth(_state.value.yearMonth)
    }

    fun loadMonth(yearMonth: YearMonth) {
        _state.update { it.copy(yearMonth = yearMonth, isLoading = true, error = null) }
        viewModelScope.launch {
            try {
                val appointments = repository.getAppointments(yearMonth.year, yearMonth.monthValue)
                _state.update { it.copy(appointments = appointments, isLoading = false) }
            } catch (e: Exception) {
                _state.update { it.copy(error = e.message, isLoading = false) }
            }
        }
    }

    fun previousMonth() {
        loadMonth(_state.value.yearMonth.minusMonths(1))
    }

    fun nextMonth() {
        loadMonth(_state.value.yearMonth.plusMonths(1))
    }

    fun selectDate(date: String?) {
        _state.update { it.copy(selectedDate = date) }
    }

    fun deleteAppointment(id: Int) {
        viewModelScope.launch {
            try {
                repository.delete(id)
                _state.update { s ->
                    s.copy(appointments = s.appointments.filter { it.id != id })
                }
            } catch (e: Exception) {
                _state.update { it.copy(error = e.message) }
            }
        }
    }

    fun createAppointment(
        request: CreateAppointmentRequest,
        onSuccess: () -> Unit,
        onError: (String) -> Unit,
    ) {
        viewModelScope.launch {
            try {
                val created = repository.createAppointment(request)
                _state.update { s -> s.copy(appointments = s.appointments + created) }
                onSuccess()
            } catch (e: Exception) {
                onError(e.message ?: "Error al crear la cita")
            }
        }
    }

    fun updateAppointment(
        id: Int,
        request: CreateAppointmentRequest,
        onSuccess: () -> Unit,
        onError: (String) -> Unit,
    ) {
        viewModelScope.launch {
            try {
                val updated = repository.updateAppointment(id, request)
                _state.update { s ->
                    s.copy(appointments = s.appointments.map { if (it.id == id) updated else it })
                }
                onSuccess()
            } catch (e: Exception) {
                onError(e.message ?: "Error al actualizar la cita")
            }
        }
    }

    fun updateStatus(id: Int, status: String) {
        viewModelScope.launch {
            try {
                val updated = repository.updateStatus(id, status)
                _state.update { s ->
                    s.copy(appointments = s.appointments.map { if (it.id == id) updated else it })
                }
            } catch (e: Exception) {
                _state.update { it.copy(error = e.message) }
            }
        }
    }

    companion object {
        fun factory(repository: CalendarRepository) = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                CalendarViewModel(repository) as T
        }
    }
}
