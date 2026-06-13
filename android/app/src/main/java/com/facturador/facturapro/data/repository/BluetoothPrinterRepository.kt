package com.facturador.facturapro.data.repository

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import com.facturador.facturapro.data.local.PrinterSettingsStore
import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.domain.model.SavedPrinter
import kotlinx.coroutines.flow.Flow

class BluetoothPrinterRepository(
    private val context: Context,
    private val store: PrinterSettingsStore,
) : PrinterRepositoryContract {
    override val selectedPrinter: Flow<SavedPrinter?> = store.selectedPrinter

    @SuppressLint("MissingPermission")
    override suspend fun pairedBluetoothPrinters(): Result<List<PrinterDevice>> = runCatching {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            ContextCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED
        ) {
            error("Permiso Bluetooth requerido para leer impresoras vinculadas.")
        }

        val bluetoothManager = context.getSystemService(BluetoothManager::class.java)
            ?: error("Bluetooth no esta disponible en este dispositivo.")
        val adapter = bluetoothManager.adapter
            ?: error("Bluetooth no esta disponible en este dispositivo.")

        if (!adapter.isEnabled) {
            error("Activa Bluetooth para ver impresoras vinculadas.")
        }

        adapter.bondedDevices
            .map { device ->
                PrinterDevice(
                    name = device.name ?: "Impresora ${device.address}",
                    address = device.address,
                )
            }
            .sortedBy { it.name.lowercase() }
    }

    override suspend fun savePrinter(printer: PrinterDevice): Result<Unit> = runCatching {
        store.save(printer)
    }

    override suspend fun clearPrinter(): Result<Unit> = runCatching {
        store.clear()
    }
}
