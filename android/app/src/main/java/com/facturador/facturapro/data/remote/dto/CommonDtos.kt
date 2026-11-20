package com.facturador.facturapro.data.remote.dto

import com.google.gson.annotations.SerializedName

data class PaginationMetaDto(
    @SerializedName("current_page")
    val currentPage: Int = 1,
    @SerializedName("last_page")
    val lastPage: Int = 1,
)

data class PaginatedResponseDto<T>(
    val data: List<T> = emptyList(),
    val meta: PaginationMetaDto? = null,
)

data class SingleDataResponseDto<T>(
    val data: T,
)

data class PdfGenerationResponseDto(
    @SerializedName("pdf_path")
    val pdfPath: String,
)
