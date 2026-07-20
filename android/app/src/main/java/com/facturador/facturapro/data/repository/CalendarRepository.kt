package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.domain.model.Appointment
import com.facturador.facturapro.domain.model.toDomain

class CalendarRepository(private val api: FacturaProApi) {

    suspend fun getAppointments(year: Int, month: Int): List<Appointment> = request {
        api.appointments(year, month).data.map { it.toDomain() }
    }

    suspend fun createAppointment(request: CreateAppointmentRequest): Appointment = request {
        api.createAppointment(request).data.toDomain()
    }

    suspend fun updateAppointment(id: Int, request: CreateAppointmentRequest): Appointment = request {
        api.updateAppointment(id, mapOf(
            "title" to request.title,
            "client_id" to request.clientId,
            "start_at" to request.startAt,
            "end_at" to request.endAt,
            "location" to request.location,
            "location_lat" to request.locationLat,
            "location_lng" to request.locationLng,
            "service_description" to request.serviceDescription,
            "observations" to request.observations,
            "contacts" to request.contacts,
            "status" to request.status
        )).data.toDomain()
    }

    suspend fun updateStatus(id: Int, status: String): Appointment = request {
        api.updateAppointment(id, mapOf("status" to status)).data.toDomain()
    }

    suspend fun delete(id: Int) = request {
        api.deleteAppointment(id)
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: Throwable) {
        throw IllegalStateException(ApiErrorMapper.message(error), error)
    }
}
