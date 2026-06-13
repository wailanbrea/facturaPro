package com.facturador.facturapro.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.facturador.facturapro.data.repository.SessionStoreContract
import com.facturador.facturapro.domain.model.AuthSession
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.sessionDataStore: DataStore<Preferences> by preferencesDataStore(name = "session")

class SessionStore(context: Context) : SessionStoreContract {
    private val dataStore = context.sessionDataStore

    override val session: Flow<AuthSession?> = dataStore.data.map { preferences ->
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
            )
        }
    }

    override suspend fun save(session: AuthSession) {
        dataStore.edit { preferences ->
            preferences[Keys.TokenType] = session.tokenType
            preferences[Keys.AccessToken] = session.accessToken
            preferences[Keys.UserId] = session.userId
            preferences[Keys.UserName] = session.userName
            preferences[Keys.UserEmail] = session.userEmail
        }
    }

    override suspend fun clear() {
        dataStore.edit { preferences -> preferences.clear() }
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
    }
}
