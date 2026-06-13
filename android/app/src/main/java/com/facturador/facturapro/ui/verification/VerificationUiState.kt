package com.facturador.facturapro.ui.verification

import com.facturador.facturapro.domain.model.InvoiceVerification

data class VerificationUiState(
    val number: String = "",
    val code: String = "",
    val isLoading: Boolean = false,
    val result: InvoiceVerification? = null,
    val errorMessage: String? = null,
)
