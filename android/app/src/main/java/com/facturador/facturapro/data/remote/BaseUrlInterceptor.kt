package com.facturador.facturapro.data.remote

import com.facturador.facturapro.data.local.ServerConfigStore
import kotlinx.coroutines.runBlocking
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.Interceptor
import okhttp3.Response

class BaseUrlInterceptor(
    private val serverConfigStore: ServerConfigStore,
    private val defaultBaseUrl: HttpUrl,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val configuredBaseUrl = runBlocking {
            serverConfigStore.currentApiBaseUrl()
        }.toHttpUrl()

        val rewrittenUrl = request.url.rewriteBaseUrl(
            fromBaseUrl = defaultBaseUrl,
            toBaseUrl = configuredBaseUrl,
        )

        return chain.proceed(request.newBuilder().url(rewrittenUrl).build())
    }
}

private fun HttpUrl.rewriteBaseUrl(
    fromBaseUrl: HttpUrl,
    toBaseUrl: HttpUrl,
): HttpUrl {
    val relativeSegments = encodedPathSegments.drop(fromBaseUrl.apiBasePathSegments().size)
    val newPathSegments = toBaseUrl.apiBasePathSegments() + relativeSegments

    val builder = newBuilder()
        .scheme(toBaseUrl.scheme)
        .host(toBaseUrl.host)
        .port(toBaseUrl.port)
        .encodedPath("/")

    newPathSegments.forEach { segment ->
        if (segment.isNotBlank()) {
            builder.addEncodedPathSegment(segment)
        }
    }

    return builder.build()
}

private fun HttpUrl.apiBasePathSegments(): List<String> =
    encodedPathSegments.filter { it.isNotBlank() }
