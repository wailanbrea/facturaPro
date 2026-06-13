package com.facturador.facturapro.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.domain.model.SavedPrinter
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.printerSettingsDataStore: DataStore<Preferences> by preferencesDataStore(name = "printer_settings")

class PrinterSettingsStore(context: Context) {
    private val dataStore = context.printerSettingsDataStore

    val selectedPrinter: Flow<SavedPrinter?> = dataStore.data.map { preferences ->
        val name = preferences[Keys.name]
        val address = preferences[Keys.address]

        if (name.isNullOrBlank() || address.isNullOrBlank()) {
            null
        } else {
            SavedPrinter(name = name, address = address)
        }
    }

    suspend fun save(printer: PrinterDevice) {
        dataStore.edit { preferences ->
            preferences[Keys.name] = printer.name
            preferences[Keys.address] = printer.address
        }
    }

    suspend fun clear() {
        dataStore.edit { preferences ->
            preferences.remove(Keys.name)
            preferences.remove(Keys.address)
        }
    }

    private object Keys {
        val name = stringPreferencesKey("selected_printer_name")
        val address = stringPreferencesKey("selected_printer_address")
    }
}
