package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.domain.model.OperationalReport
import com.facturador.facturapro.domain.model.ReportFilters

class ReportRepository(
    private val api: FacturaProApi,
) : ReportRepositoryContract {
    override suspend fun load(filters: ReportFilters): Result<OperationalReport> = runCatching {
        api.reports(
            dateFrom = filters.dateFrom?.trim().takeUnless { it.isNullOrEmpty() },
            dateTo = filters.dateTo?.trim().takeUnless { it.isNullOrEmpty() },
            currencyCode = filters.currencyCode?.trim().takeUnless { it.isNullOrEmpty() },
        ).data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
