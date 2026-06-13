package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.ClientDraft
import com.facturador.facturapro.domain.model.ClientRecord
import com.google.gson.annotations.SerializedName

data class ClientDto(
    val id: Long,
    val name: String,
    @SerializedName("tax_id")
    val taxId: String? = null,
    val address: String? = null,
    val city: String? = null,
    val phone: String? = null,
    val email: String? = null,
    val notes: String? = null,
    @SerializedName("is_active")
    val isActive: Boolean = true,
)

data class ClientUpsertDto(
    val name: String,
    @SerializedName("tax_id")
    val taxId: String? = null,
    val address: String? = null,
    val city: String? = null,
    val phone: String? = null,
    val email: String? = null,
    val notes: String? = null,
)

fun ClientDto.toDomain(): ClientRecord = ClientRecord(
    id = id,
    name = name,
    taxId = taxId,
    address = address,
    city = city,
    phone = phone,
    email = email,
    notes = notes,
    isActive = isActive,
)

fun ClientDraft.toRemote(): ClientUpsertDto = ClientUpsertDto(
    name = name.trim(),
    taxId = taxId?.trim().takeUnless { it.isNullOrEmpty() },
    address = address?.trim().takeUnless { it.isNullOrEmpty() },
    city = city?.trim().takeUnless { it.isNullOrEmpty() },
    phone = phone?.trim().takeUnless { it.isNullOrEmpty() },
    email = email?.trim().takeUnless { it.isNullOrEmpty() },
    notes = notes?.trim().takeUnless { it.isNullOrEmpty() },
)
