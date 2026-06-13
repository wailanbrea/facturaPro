package com.facturador.facturapro.domain.model

data class AuthSession(
    val tokenType: String,
    val accessToken: String,
    val userId: Long,
    val userName: String,
    val userEmail: String,
)
