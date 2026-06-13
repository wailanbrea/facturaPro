package com.facturador.facturapro.domain.model

data class ClientRecord(
    val id: Long,
    val name: String,
    val taxId: String?,
    val address: String?,
    val city: String?,
    val phone: String?,
    val email: String?,
    val notes: String?,
    val isActive: Boolean,
)

data class ClientDraft(
    val name: String,
    val taxId: String?,
    val address: String?,
    val city: String?,
    val phone: String?,
    val email: String?,
    val notes: String?,
)
