package com.facturador.facturapro.data.remote

import com.facturador.facturapro.data.local.SessionStore
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(
    private val sessionStore: SessionStore,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val authorization = runBlocking { sessionStore.currentAuthorizationHeader() }

        val authenticatedRequest = if (authorization.isNullOrBlank()) {
            request
        } else {
            request.newBuilder()
                .header("Authorization", authorization)
                .header("Accept", "application/json")
                .build()
        }

        return chain.proceed(authenticatedRequest)
    }
}
