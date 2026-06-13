package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.LoginRequestDto
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.AuthSession
import kotlinx.coroutines.flow.Flow

class AuthRepository(
    private val api: FacturaProApi,
    private val sessionStore: SessionStoreContract,
) : AuthRepositoryContract {
    override val session: Flow<AuthSession?> = sessionStore.session

    override suspend fun login(email: String, password: String): Result<AuthSession> = runCatching {
        api.login(
            LoginRequestDto(
                email = email.trim(),
                password = password,
                deviceName = "FacturaPro Android",
            ),
        ).toDomain()
    }.fold(
        onSuccess = { session ->
            sessionStore.save(session)
            Result.success(session)
        },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    override suspend fun logout() {
        runCatching { api.logout() }
        sessionStore.clear()
    }
}
