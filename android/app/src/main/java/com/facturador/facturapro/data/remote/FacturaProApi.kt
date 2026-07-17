package com.facturador.facturapro.data.remote

import com.facturador.facturapro.data.remote.dto.AppointmentListResponse
import com.facturador.facturapro.data.remote.dto.AppointmentResponse
import com.facturador.facturapro.data.remote.dto.BootstrapResponseDto
import com.facturador.facturapro.data.remote.dto.CreateAppointmentRequest
import com.facturador.facturapro.data.remote.dto.ClientDto
import com.facturador.facturapro.data.remote.dto.ClientUpsertDto
import com.facturador.facturapro.data.remote.dto.DashboardResponseDto
import com.facturador.facturapro.data.remote.dto.InvoiceDto
import com.facturador.facturapro.data.remote.dto.InvoiceUpsertDto
import com.facturador.facturapro.data.remote.dto.InvoiceVerificationResponseDto
import com.facturador.facturapro.data.remote.dto.LoginRequestDto
import com.facturador.facturapro.data.remote.dto.LoginResponseDto
import com.facturador.facturapro.data.remote.dto.PaginatedResponseDto
import com.facturador.facturapro.data.remote.dto.PdfGenerationResponseDto
import com.facturador.facturapro.data.remote.dto.ReportResponseDto
import com.facturador.facturapro.data.remote.dto.SingleDataResponseDto
import com.facturador.facturapro.data.remote.dto.TechnicalReportDto
import com.facturador.facturapro.data.remote.dto.TechnicalReportSettingDto
import com.facturador.facturapro.data.remote.dto.TechnicalReportUpsertDto
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming

interface FacturaProApi {
    @POST("login")
    suspend fun login(@Body body: LoginRequestDto): LoginResponseDto

    @POST("logout")
    suspend fun logout()

    @GET("settings/bootstrap")
    suspend fun bootstrap(): BootstrapResponseDto

    @GET("dashboard")
    suspend fun dashboard(): DashboardResponseDto

    @GET("reports")
    suspend fun reports(
        @Query("date_from") dateFrom: String? = null,
        @Query("date_to") dateTo: String? = null,
        @Query("currency_code") currencyCode: String? = null,
    ): ReportResponseDto

    @GET("report-settings")
    suspend fun reportSettings(): SingleDataResponseDto<TechnicalReportSettingDto>

    @GET("technical-reports")
    suspend fun technicalReports(
        @Query("search") search: String? = null,
        @Query("per_page") perPage: Int = 50,
    ): PaginatedResponseDto<TechnicalReportDto>

    @GET("technical-reports/{report}")
    suspend fun technicalReport(@Path("report") reportId: Long): SingleDataResponseDto<TechnicalReportDto>

    @Streaming
    @GET("technical-reports/{report}/preview")
    suspend fun previewTechnicalReport(@Path("report") reportId: Long): Response<ResponseBody>

    @POST("technical-reports")
    suspend fun createTechnicalReport(@Body body: TechnicalReportUpsertDto): SingleDataResponseDto<TechnicalReportDto>

    @PUT("technical-reports/{report}")
    suspend fun updateTechnicalReport(
        @Path("report") reportId: Long,
        @Body body: TechnicalReportUpsertDto,
    ): SingleDataResponseDto<TechnicalReportDto>

    @DELETE("technical-reports/{report}")
    suspend fun deleteTechnicalReport(@Path("report") reportId: Long): Response<ResponseBody>

    @POST("technical-reports/{report}/generate-pdf")
    suspend fun generateTechnicalReportPdf(@Path("report") reportId: Long): PdfGenerationResponseDto

    @Streaming
    @GET("technical-reports/{report}/download-pdf")
    suspend fun downloadTechnicalReportPdf(@Path("report") reportId: Long): Response<ResponseBody>

    @GET("clients")
    suspend fun clients(
        @Query("search") search: String? = null,
        @Query("per_page") perPage: Int = 50,
    ): PaginatedResponseDto<ClientDto>

    @POST("clients")
    suspend fun createClient(@Body body: ClientUpsertDto): SingleDataResponseDto<ClientDto>

    @PUT("clients/{client}")
    suspend fun updateClient(
        @Path("client") clientId: Long,
        @Body body: ClientUpsertDto,
    ): SingleDataResponseDto<ClientDto>

    @GET("invoices")
    suspend fun invoices(
        @Query("search") search: String? = null,
        @Query("per_page") perPage: Int = 50,
    ): PaginatedResponseDto<InvoiceDto>

    @GET("invoices/verify")
    suspend fun verifyInvoice(
        @Query("number") number: String,
        @Query("code") code: String,
    ): InvoiceVerificationResponseDto

    @GET("invoices/{invoice}")
    suspend fun invoice(@Path("invoice") invoiceId: Long): SingleDataResponseDto<InvoiceDto>

    @Streaming
    @GET("invoices/{invoice}/preview")
    suspend fun previewInvoice(@Path("invoice") invoiceId: Long): Response<ResponseBody>

    @Streaming
    @GET("invoices/{invoice}/issue-preview")
    suspend fun previewInvoiceIssue(@Path("invoice") invoiceId: Long): Response<ResponseBody>

    @Streaming
    @POST("invoices/preview")
    suspend fun previewInvoiceDraft(@Body body: InvoiceUpsertDto): Response<ResponseBody>

    @POST("invoices")
    suspend fun createInvoice(@Body body: InvoiceUpsertDto): SingleDataResponseDto<InvoiceDto>

    @PUT("invoices/{invoice}")
    suspend fun updateInvoice(
        @Path("invoice") invoiceId: Long,
        @Body body: InvoiceUpsertDto,
    ): SingleDataResponseDto<InvoiceDto>

    @POST("invoices/{invoice}/issue")
    suspend fun issueInvoice(@Path("invoice") invoiceId: Long): SingleDataResponseDto<InvoiceDto>

    @POST("invoices/{invoice}/generate-pdf")
    suspend fun generateInvoicePdf(@Path("invoice") invoiceId: Long): PdfGenerationResponseDto

    @Streaming
    @GET("invoices/{invoice}/download-pdf")
    suspend fun downloadInvoicePdf(@Path("invoice") invoiceId: Long): Response<ResponseBody>

    @POST("invoices/{invoice}/convert")
    suspend fun convertInvoice(@Path("invoice") invoiceId: Long): SingleDataResponseDto<InvoiceDto>

    @POST("invoices/{invoice}/mark-paid")
    suspend fun markInvoicePaid(
        @Path("invoice") invoiceId: Long,
        @Body body: Map<String, @JvmSuppressWildcards Any?>,
    ): SingleDataResponseDto<InvoiceDto>

    @GET("appointments")
    suspend fun appointments(
        @Query("year") year: Int,
        @Query("month") month: Int,
    ): AppointmentListResponse

    @POST("appointments")
    suspend fun createAppointment(@Body body: CreateAppointmentRequest): AppointmentResponse

    @PUT("appointments/{appointment}")
    suspend fun updateAppointment(
        @Path("appointment") id: Int,
        @Body body: Map<String, @JvmSuppressWildcards Any?>,
    ): AppointmentResponse

    @DELETE("appointments/{appointment}")
    suspend fun deleteAppointment(@Path("appointment") id: Int): Response<ResponseBody>

    @POST("device-tokens")
    suspend fun registerDeviceToken(
        @Header("Authorization") auth: String,
        @Body body: Map<String, @JvmSuppressWildcards String>,
    ): Response<ResponseBody>
}
