package com.facturador.facturapro.data.repository

import com.facturador.facturapro.data.remote.ApiErrorMapper
import com.facturador.facturapro.data.remote.FacturaProApi
import com.facturador.facturapro.data.remote.dto.toDomain
import com.facturador.facturapro.data.remote.dto.toRemote
import com.facturador.facturapro.domain.model.ClientDraft
import com.facturador.facturapro.domain.model.ClientRecord

class ClientRepository(
    private val api: FacturaProApi,
) {
    suspend fun list(search: String? = null): Result<List<ClientRecord>> = runCatching {
        api.clients(search = search?.trim().takeUnless { it.isNullOrEmpty() }).data
            .map { it.toDomain() }
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    suspend fun create(draft: ClientDraft): Result<ClientRecord> = runCatching {
        api.createClient(draft.toRemote()).data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )

    suspend fun update(id: Long, draft: ClientDraft): Result<ClientRecord> = runCatching {
        api.updateClient(id, draft.toRemote()).data.toDomain()
    }.fold(
        onSuccess = { Result.success(it) },
        onFailure = { error ->
            Result.failure(IllegalStateException(ApiErrorMapper.message(error), error))
        },
    )
}
