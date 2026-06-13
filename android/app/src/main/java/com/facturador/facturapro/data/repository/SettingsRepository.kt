package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.BootstrapCatalogs

class SettingsRepository(
    private val api: FacturaProApi,
) : SettingsRepositoryContract {
    override suspend fun loadBootstrap(): Result<BootstrapCatalogs> = runCatching {
        api.bootstrap().data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
