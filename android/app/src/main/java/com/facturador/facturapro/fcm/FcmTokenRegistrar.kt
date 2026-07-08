package com.facturador.facturapro.fcm

import android.content.Context
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.facturador.facturapro.data.local.ServerConfigStore
import com.facturador.facturapro.data.local.SessionStore
import com.facturador.facturapro.data.remote.ApiClientFactory
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.tasks.await

private val Context.fcmPrefs by preferencesDataStore("fcm_prefs")
private val KEY_REGISTERED_TOKEN = stringPreferencesKey("registered_token")

object FcmTokenRegistrar {

    suspend fun ensureRegistered(context: Context) {
        val token = try {
            FirebaseMessaging.getInstance().token.await()
        } catch (_: Exception) {
            return
        }

        val prefs = context.fcmPrefs.data.firstOrNull()
        val alreadyRegistered = prefs?.get(KEY_REGISTERED_TOKEN)
        if (alreadyRegistered == token) return

        register(context, token)
    }

    suspend fun register(context: Context, token: String) {
        val sessionStore = SessionStore(context)
        val serverConfigStore = ServerConfigStore(context)
        val session = sessionStore.session.firstOrNull() ?: return
        val api = ApiClientFactory.create(
            sessionStore = sessionStore,
            serverConfigStore = serverConfigStore,
        )

        try {
            api.registerDeviceToken(
                "${session.tokenType} ${session.accessToken}",
                mapOf("token" to token, "platform" to "android"),
            )
            context.fcmPrefs.updateData { prefs ->
                prefs.toMutablePreferences().apply { set(KEY_REGISTERED_TOKEN, token) }
            }
        } catch (_: Exception) {
            // Will retry on next launch
        }
    }
}
