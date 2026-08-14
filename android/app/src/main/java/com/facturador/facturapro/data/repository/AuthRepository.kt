package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.LoginRequestDto
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.AuthSession
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.withTimeoutOrNull

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
        val currentSession = sessionStore.session.first()
        val authorization = currentSession?.let { "${it.tokenType} ${it.accessToken}" }

        // Local logout drives the UI and must be immediate even without network.
        sessionStore.clear()

        // Server token revocation is best-effort and bounded. The explicit
        // header remains available after the local session has been cleared.
        runCatching {
            withTimeoutOrNull(1_500) {
                api.logout(authorization)
            }
        }
    }
}
