package com.facturador.facturapro.ui.settings

import com.facturador.facturapro.domain.model.PrinterDevice
import com.facturador.facturapro.domain.model.SavedPrinter

data class SettingsUiState(
    val selectedPrinter: SavedPrinter? = null,
    val availablePrinters: List<PrinterDevice> = emptyList(),
    val isLoadingPrinters: Boolean = false,
    val printerMessage: String? = null,
)
