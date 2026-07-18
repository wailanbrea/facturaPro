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

interface ServerConfigStoreContract {
    val apiBaseUrl: Flow<String>

    suspend fun currentApiBaseUrl(): String

    suspend fun saveApiBaseUrl(rawValue: String): Result<String>

    suspend fun resetApiBaseUrl(): String
}

class ServerConfigStore(context: Context) : ServerConfigStoreContract {
    private val dataStore = context.serverConfigDataStore

    override val apiBaseUrl: Flow<String> = dataStore.data.map { preferences ->
        preferences[Keys.apiBaseUrl]?.normalizeApiBaseUrlOrNull() ?: DEFAULT_API_BASE_URL
    }

    override suspend fun currentApiBaseUrl(): String = apiBaseUrl.first()

    override suspend fun saveApiBaseUrl(rawValue: String): Result<String> = runCatching {
        val normalized = rawValue.normalizeApiBaseUrlOrNull()
            ?: error("URL invalida. Usa un dominio HTTPS, por ejemplo facturapro.bsolutions.dev")

        val parsed = normalized.toHttpUrl()

        // El texto plano (HTTP) solo se admite contra hosts locales o de la red
        // local (WiFi de desarrollo). Los servidores publicos deben usar HTTPS.
        require(parsed.isHttps || parsed.host.isLocalOrPrivateHost()) {
            "Por seguridad, usa HTTPS para servidores publicos."
        }

        dataStore.edit { preferences ->
            preferences[Keys.apiBaseUrl] = normalized
        }

        normalized
    }

    override suspend fun resetApiBaseUrl(): String {
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

/**
 * Hosts contra los que se admite HTTP en claro: loopback del emulador,
 * localhost y las direcciones IPv4 privadas (RFC 1918) de una red WiFi local.
 */
internal fun String.isLocalOrPrivateHost(): Boolean {
    if (this == "localhost" || this == "127.0.0.1" || this == "10.0.2.2") return true

    val octets = split(".")
    if (octets.size != 4) return false
    val (a, b) = octets.take(2).map { it.toIntOrNull() ?: return false }
    if (octets.drop(2).any { (it.toIntOrNull() ?: return false) !in 0..255 }) return false

    return when (a) {
        10 -> true                       // 10.0.0.0/8
        172 -> b in 16..31               // 172.16.0.0/12
        192 -> b == 168                  // 192.168.0.0/16
        else -> false
    }
}

private fun String.normalizeApiBaseUrlOrNull(): String? {
    val trimmed = trim()
    if (trimmed.isBlank()) return null

    val withScheme = if (trimmed.contains("://")) {
        trimmed
    } else {
        // Sin esquema explicito: HTTP para hosts locales/LAN, HTTPS para el resto.
        val host = trimmed.substringBefore('/').substringBefore(':')
        if (host.isLocalOrPrivateHost()) "http://$trimmed" else "https://$trimmed"
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
