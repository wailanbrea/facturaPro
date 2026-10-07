package com.facturador.facturapro.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.core.stringSetPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.facturador.facturapro.data.repository.SessionStoreContract
import com.facturador.facturapro.domain.model.AuthSession
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.sessionDataStore: DataStore<Preferences> by preferencesDataStore(name = "session")

class SessionStore(context: Context) : SessionStoreContract {
    private val dataStore = context.sessionDataStore
    private val volatileSession = MutableStateFlow<AuthSession?>(null)

    private val persistedSession: Flow<AuthSession?> = dataStore.data.map { preferences ->
        val accessToken = preferences[Keys.AccessToken].orEmpty()
        val userId = preferences[Keys.UserId] ?: return@map null

        if (accessToken.isBlank()) {
            null
        } else {
            AuthSession(
                tokenType = preferences[Keys.TokenType].orEmpty().ifBlank { "Bearer" },
                accessToken = accessToken,
                userId = userId,
                userName = preferences[Keys.UserName].orEmpty(),
                userEmail = preferences[Keys.UserEmail].orEmpty(),
                permissions = preferences[Keys.Permissions].orEmpty(),
            )
        }
    }

    override val session: Flow<AuthSession?> = combine(persistedSession, volatileSession) { persisted, current ->
        current ?: persisted
    }

    override val rememberSession: Flow<Boolean> = dataStore.data.map { preferences ->
        preferences[Keys.RememberSession] ?: true
    }

    override val biometricEnabled: Flow<Boolean> = dataStore.data.map { preferences ->
        preferences[Keys.BiometricEnabled] ?: false
    }

    override val savedEmail: Flow<String> = dataStore.data.map { preferences ->
        preferences[Keys.SavedEmail].orEmpty().ifBlank { preferences[Keys.UserEmail].orEmpty() }
    }

    override suspend fun save(session: AuthSession, rememberSession: Boolean, biometricEnabled: Boolean) {
        volatileSession.value = session
        dataStore.edit { preferences ->
            preferences[Keys.RememberSession] = rememberSession
            preferences[Keys.BiometricEnabled] = rememberSession && biometricEnabled
            preferences[Keys.SavedEmail] = session.userEmail
            if (rememberSession) {
                preferences[Keys.TokenType] = session.tokenType
                preferences[Keys.AccessToken] = session.accessToken
                preferences[Keys.UserId] = session.userId
                preferences[Keys.UserName] = session.userName
                preferences[Keys.UserEmail] = session.userEmail
                preferences[Keys.Permissions] = session.permissions
            } else {
                preferences.remove(Keys.TokenType)
                preferences.remove(Keys.AccessToken)
                preferences.remove(Keys.UserId)
                preferences.remove(Keys.UserName)
                preferences.remove(Keys.UserEmail)
                preferences.remove(Keys.Permissions)
            }
        }
    }

    override suspend fun clear() {
        volatileSession.value = null
        dataStore.edit { preferences ->
            preferences.remove(Keys.TokenType)
            preferences.remove(Keys.AccessToken)
            preferences.remove(Keys.UserId)
            preferences.remove(Keys.UserName)
            preferences.remove(Keys.UserEmail)
            preferences.remove(Keys.Permissions)
            preferences[Keys.BiometricEnabled] = false
        }
    }

    suspend fun currentAuthorizationHeader(): String? {
        val session = session.first() ?: return null

        return "${session.tokenType} ${session.accessToken}"
    }

    private object Keys {
        val TokenType = stringPreferencesKey("token_type")
        val AccessToken = stringPreferencesKey("access_token")
        val UserId = longPreferencesKey("user_id")
        val UserName = stringPreferencesKey("user_name")
        val UserEmail = stringPreferencesKey("user_email")
        val Permissions = stringSetPreferencesKey("permissions")
        val RememberSession = booleanPreferencesKey("remember_session")
        val BiometricEnabled = booleanPreferencesKey("biometric_enabled")
        val SavedEmail = stringPreferencesKey("saved_email")
    }
}
