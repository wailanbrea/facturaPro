package com.facturador.facturapro.data.remote.dto

import com.google.gson.annotations.SerializedName

data class PaginatedResponseDto<T>(
    val data: List<T> = emptyList(),
)

data class SingleDataResponseDto<T>(
    val data: T,
)

data class PdfGenerationResponseDto(
    @SerializedName("pdf_path")
    val pdfPath: String,
)
