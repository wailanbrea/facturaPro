package com.facturador.facturapro.domain.model

import com.facturador.facturapro.data.remote.dto.AppointmentDto
import com.facturador.facturapro.data.remote.dto.ContactDto

data class Appointment(
    val id: Int,
    val title: String,
    val clientId: Int?,
    val clientName: String?,
    val createdById: Int,
    val startAt: String,
    val endAt: String,
    val location: String?,
    val contacts: List<AppointmentContact>,
    val observations: String?,
    val serviceDescription: String?,
    val status: AppointmentStatus,
    val creatorName: String?,
)

data class AppointmentContact(
    val name: String?,
    val phone: String?,
    val email: String?,
)

enum class AppointmentStatus(val key: String, val label: String, val colorHex: String) {
    PENDING("pending", "Pendiente", "#3b82f6"),
    IN_PROGRESS("in_progress", "En curso", "#f59e0b"),
    DONE("done", "Realizado", "#10b981"),
    URGENT("urgent", "Urgente", "#ef4444"),
    PRIORITY("priority", "Prioridad", "#8b5cf6"),
    CANCELLED("cancelled", "Cancelada", "#9ca3af");

    companion object {
        fun from(key: String) = entries.firstOrNull { it.key == key } ?: PENDING
    }
}

fun AppointmentDto.toDomain() = Appointment(
    id = id,
    title = title,
    clientId = clientId,
    clientName = clientName ?: client?.name,
    createdById = createdById,
    startAt = startAt,
    endAt = endAt,
    location = location,
    contacts = contacts?.map { it.toDomain() } ?: emptyList(),
    observations = observations,
    serviceDescription = serviceDescription,
    status = AppointmentStatus.from(status),
    creatorName = creator?.name,
)

fun ContactDto.toDomain() = AppointmentContact(name = name, phone = phone, email = email)
