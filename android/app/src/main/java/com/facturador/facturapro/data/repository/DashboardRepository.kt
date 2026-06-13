package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.DashboardSummary

class DashboardRepository(
    private val api: FacturaProApi,
) : DashboardRepositoryContract {
    override suspend fun load(): Result<DashboardSummary> = runCatching {
        api.dashboard().toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
