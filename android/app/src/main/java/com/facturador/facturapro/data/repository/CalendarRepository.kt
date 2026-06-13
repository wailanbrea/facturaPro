package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.domain.model.Appointment
import com.facturador.facturapro.domain.model.toDomain

class CalendarRepository(private val api: FacturaProApi) {

    suspend fun getAppointments(year: Int, month: Int): List<Appointment> =
        api.appointments(year, month).data.map { it.toDomain() }

    suspend fun createAppointment(request: CreateAppointmentRequest): Appointment =
        api.createAppointment(request).data.toDomain()

    suspend fun updateStatus(id: Int, status: String): Appointment =
        api.updateAppointment(id, mapOf("status" to status)).data.toDomain()

    suspend fun delete(id: Int) {
        api.deleteAppointment(id)
    }
}
