package com.facturador.facturapro.ui.settings

import com.facturador.facturapro.data.repository.PrinterRepositoryContract
import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.domain.model.SavedPrinter
import com.facturador.facturapro.testutil.MainDispatcherRule
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class SettingsViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun load_bluetooth_printers_updates_available_printers() = runTest {
        val printers = listOf(
            PrinterDevice(name = "Impresora Caja", address = "00:11:22:33:44:55"),
        )
        val repository = FakePrinterRepository(printersResult = Result.success(printers))
        val viewModel = SettingsViewModel(repository)

        advanceUntilIdle()
        viewModel.loadBluetoothPrinters()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(printers, state.availablePrinters)
        assertFalse(state.isLoadingPrinters)
        assertNull(state.printerMessage)
    }

    @Test
    fun save_printer_persists_selected_printer_in_state() = runTest {
        val printer = PrinterDevice(name = "Impresora Taller", address = "AA:BB:CC:DD:EE:FF")
        val repository = FakePrinterRepository()
        val viewModel = SettingsViewModel(repository)

        advanceUntilIdle()
        viewModel.savePrinter(printer)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals(SavedPrinter(name = printer.name, address = printer.address), state.selectedPrinter)
        assertEquals("Impresora guardada.", state.printerMessage)
    }
}

private class FakePrinterRepository(
    private val printersResult: Result<List<PrinterDevice>> = Result.success(emptyList()),
) : PrinterRepositoryContract {
    private val selectedPrinterState = MutableStateFlow<SavedPrinter?>(null)

    override val selectedPrinter: Flow<SavedPrinter?> = selectedPrinterState

    override suspend fun pairedBluetoothPrinters(): Result<List<PrinterDevice>> = printersResult

    override suspend fun savePrinter(printer: PrinterDevice): Result<Unit> {
        selectedPrinterState.value = SavedPrinter(name = printer.name, address = printer.address)
        return Result.success(Unit)
    }

    override suspend fun clearPrinter(): Result<Unit> {
        selectedPrinterState.value = null
        return Result.success(Unit)
    }
}
