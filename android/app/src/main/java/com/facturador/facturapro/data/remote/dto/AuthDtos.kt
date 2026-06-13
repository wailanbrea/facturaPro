package com.facturador.facturapro.data.remote.dto

import com.facturador.facturapro.domain.model.AuthSession
import com.google.gson.annotations.SerializedName

data class LoginRequestDto(
    val email: String,
    val password: String,
    @SerializedName("device_name")
    val deviceName: String,
)

data class LoginResponseDto(
    @SerializedName("token_type")
    val tokenType: String,
    @SerializedName("access_token")
    val accessToken: String,
    val user: UserDto,
)

data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
)

fun LoginResponseDto.toDomain(): AuthSession = AuthSession(
    tokenType = tokenType,
    accessToken = accessToken,
    userId = user.id,
    userName = user.name,
    userEmail = user.email,
)
