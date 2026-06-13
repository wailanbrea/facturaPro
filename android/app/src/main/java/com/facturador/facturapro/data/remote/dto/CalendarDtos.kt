package com.facturador.facturapro.data.remote.dto

import com.google.gson.annotations.SerializedName

data class AppointmentDto(
    val id: Int,
    val title: String,
    @SerializedName("client_id") val clientId: Int?,
    @SerializedName("client_name") val clientName: String?,
    @SerializedName("created_by") val createdById: Int,
    @SerializedName("start_at") val startAt: String,
    @SerializedName("end_at") val endAt: String,
    val location: String?,
    val contacts: List<ContactDto>?,
    val observations: String?,
    @SerializedName("service_description") val serviceDescription: String?,
    val status: String,
    @SerializedName("created_at") val createdAt: String?,
    val creator: UserRefDto?,
    val client: ClientRefDto?,
)

data class ContactDto(
    val name: String?,
    val phone: String?,
    val email: String?,
)

data class UserRefDto(val id: Int, val name: String)
data class ClientRefDto(val id: Int, val name: String)

data class AppointmentListResponse(val data: List<AppointmentDto>)
data class AppointmentResponse(val data: AppointmentDto)

data class CreateAppointmentRequest(
    val title: String,
    @SerializedName("client_id") val clientId: Int?,
    @SerializedName("start_at") val startAt: String,
    @SerializedName("end_at") val endAt: String,
    val location: String?,
    @SerializedName("service_description") val serviceDescription: String?,
    val observations: String?,
    val contacts: List<ContactDto>?,
    val status: String,
)

data class UpdateStatusRequest(val status: String)
