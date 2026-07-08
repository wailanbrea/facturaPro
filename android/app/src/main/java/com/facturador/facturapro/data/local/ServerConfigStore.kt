package com.facturador.facturapro.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.facturador.facturapro.BuildConfig
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull

private val Context.serverConfigDataStore: DataStore<Preferences> by preferencesDataStore(name = "server_config")

class ServerConfigStore(context: Context) {
    private val dataStore = context.serverConfigDataStore

    val apiBaseUrl: Flow<String> = dataStore.data.map { preferences ->
        preferences[Keys.apiBaseUrl]?.normalizeApiBaseUrlOrNull() ?: DEFAULT_API_BASE_URL
    }

    suspend fun currentApiBaseUrl(): String = apiBaseUrl.first()

    suspend fun saveApiBaseUrl(rawValue: String): Result<String> = runCatching {
        val normalized = rawValue.normalizeApiBaseUrlOrNull()
            ?: error("URL invalida. Usa un dominio HTTPS, por ejemplo facturapro.bsolutions.dev")

        val parsed = normalized.toHttpUrl()
        val isLocal = parsed.host == "localhost" ||
            parsed.host == "127.0.0.1" ||
            parsed.host == "10.0.2.2"

        require(parsed.isHttps || isLocal) {
            "Por seguridad, usa HTTPS para servidores publicos."
        }

        dataStore.edit { preferences ->
            preferences[Keys.apiBaseUrl] = normalized
        }

        normalized
    }

    suspend fun resetApiBaseUrl(): String {
        dataStore.edit { preferences ->
            preferences.remove(Keys.apiBaseUrl)
        }

        return DEFAULT_API_BASE_URL
    }

    private object Keys {
        val apiBaseUrl = stringPreferencesKey("api_base_url")
    }

    companion object {
        val DEFAULT_API_BASE_URL: String = BuildConfig.API_BASE_URL.normalizeApiBaseUrlOrNull()
            ?: "https://facturapro.bsolutions.dev/api/"
    }
}

private fun String.normalizeApiBaseUrlOrNull(): String? {
    val trimmed = trim()
    if (trimmed.isBlank()) return null

    val withScheme = if (trimmed.contains("://")) {
        trimmed
    } else {
        "https://$trimmed"
    }

    val parsed = withScheme.toHttpUrlOrNull() ?: return null
    val builder = parsed.newBuilder()

    if (parsed.encodedPath == "/" || parsed.encodedPath.isBlank()) {
        builder.encodedPath("/api/")
    } else if (!parsed.encodedPath.endsWith("/")) {
        builder.encodedPath(parsed.encodedPath + "/")
    }

    return builder.build().toString()
}
