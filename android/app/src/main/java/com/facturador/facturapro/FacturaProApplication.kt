package com.facturador.facturapro

import android.app.Application
import com.facturador.facturapro.di.AppContainer
import com.facturador.facturapro.fcm.FcmTokenRegistrar
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class FacturaProApplication : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        CoroutineScope(Dispatchers.IO).launch {
            FcmTokenRegistrar.ensureRegistered(applicationContext)
        }
    }
}
