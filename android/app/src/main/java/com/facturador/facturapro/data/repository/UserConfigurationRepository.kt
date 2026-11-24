package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.UserConfigurationOverview

class UserConfigurationRepository(
    private val api: FacturaProApi,
) {
    suspend fun load(): Result<UserConfigurationOverview> = runCatching {
        api.userConfigurations().data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
