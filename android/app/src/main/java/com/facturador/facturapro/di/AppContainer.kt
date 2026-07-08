package com.facturador.facturapro.di

import android.content.Context
import com.facturador.facturapro.data.local.PrinterSettingsStore
import com.facturador.facturapro.data.local.ServerConfigStore
import com.facturador.facturapro.data.local.SessionStore
import com.facturador.facturapro.data.remote.ApiClientFactory
import com.facturador.facturapro.data.repository.AuthRepository
import com.facturador.facturapro.data.repository.BluetoothPrinterRepository
import com.facturador.facturapro.data.repository.CalendarRepository
import com.facturador.facturapro.data.repository.ClientRepository
import com.facturador.facturapro.data.repository.DashboardRepository
import com.facturador.facturapro.data.repository.InvoiceRepository
import com.facturador.facturapro.data.repository.ReportRepository
import com.facturador.facturapro.data.repository.SettingsRepository
import com.facturador.facturapro.data.repository.TechnicalReportRepository

class AppContainer(context: Context) {
    private val appContext = context.applicationContext
    val sessionStore = SessionStore(appContext)
    val serverConfigStore = ServerConfigStore(appContext)
    private val printerSettingsStore = PrinterSettingsStore(appContext)
    private val api = ApiClientFactory.create(
        sessionStore = sessionStore,
        serverConfigStore = serverConfigStore,
    )

    val authRepository = AuthRepository(api, sessionStore)
    val clientRepository = ClientRepository(api)
    val invoiceRepository = InvoiceRepository(api, appContext)
    val reportRepository = ReportRepository(api)
    val technicalReportRepository = TechnicalReportRepository(api, appContext)
    val settingsRepository = SettingsRepository(api)
    val dashboardRepository = DashboardRepository(api)
    val printerRepository = BluetoothPrinterRepository(appContext, printerSettingsStore)
    val calendarRepository = CalendarRepository(api)
}
