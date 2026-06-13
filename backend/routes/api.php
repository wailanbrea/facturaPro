<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportSettingController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TechnicalReportController;
use App\Http\Controllers\Api\TechnicalReportPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn (): array => [
    'status' => 'ok',
    'app' => config('app.name'),
    'environment' => app()->environment(),
]);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', DashboardController::class)->middleware('permission:ver_factura');

    Route::get('/settings/bootstrap', [SettingsController::class, 'bootstrap']);
    Route::get('/currencies', [SettingsController::class, 'currencies']);
    Route::get('/taxes', [SettingsController::class, 'taxes']);
    Route::get('/payment-terms', [SettingsController::class, 'paymentTerms']);
    Route::get('/warranties', [SettingsController::class, 'warranties']);
    Route::get('/bank-accounts', [SettingsController::class, 'bankAccounts']);
    Route::get('/fiscal-profiles', [SettingsController::class, 'fiscalProfiles']);

    Route::get('/reports', ReportController::class)->middleware('permission:ver_reportes');
    Route::get('/report-settings', [ReportSettingController::class, 'show'])->middleware('permission:ver_informes');
    Route::get('/technical-reports', [TechnicalReportController::class, 'index'])->middleware('permission:ver_informes');
    Route::post('/technical-reports', [TechnicalReportController::class, 'store'])->middleware('permission:crear_informes');
    Route::get('/technical-reports/{technicalReport}', [TechnicalReportController::class, 'show'])->middleware('permission:ver_informes');
    Route::put('/technical-reports/{technicalReport}', [TechnicalReportController::class, 'update'])->middleware('permission:editar_informes');
    Route::delete('/technical-reports/{technicalReport}', [TechnicalReportController::class, 'destroy'])->middleware('permission:eliminar_informes');
    Route::get('/technical-reports/{technicalReport}/preview', [TechnicalReportPdfController::class, 'preview'])->middleware('permission:ver_informes');
    Route::post('/technical-reports/{technicalReport}/generate-pdf', [TechnicalReportPdfController::class, 'generate'])->middleware('permission:descargar_informes');
    Route::get('/technical-reports/{technicalReport}/download-pdf', [TechnicalReportPdfController::class, 'download'])->middleware('permission:descargar_informes');

    Route::apiResource('clients', ClientController::class)->middleware('permission:gestionar_clientes');

    Route::post('/device-tokens', [AppointmentController::class, 'registerDeviceToken']);

    Route::middleware('permission:ver_calendario')->group(function (): void {
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('permission:gestionar_citas');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:gestionar_citas');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->middleware('permission:gestionar_citas');
    });

    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:ver_factura');
    Route::get('/invoices/verify', [InvoiceController::class, 'verify'])->middleware('permission:ver_factura');
    Route::post('/invoices/preview', [InvoiceController::class, 'previewDraft'])->middleware('permission:crear_factura');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:crear_factura');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:ver_factura');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->middleware('permission:ver_factura');
    Route::get('/invoices/{invoice}/issue-preview', [InvoiceController::class, 'previewIssue'])->middleware('permission:emitir_factura');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:editar_factura');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:editar_factura');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('permission:emitir_factura');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:anular_factura');
    Route::post('/invoices/{invoice}/generate-pdf', [InvoiceController::class, 'generatePdf'])->middleware('permission:descargar_pdf');
    Route::get('/invoices/{invoice}/download-pdf', [InvoiceController::class, 'downloadPdf'])->middleware('permission:descargar_pdf');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware('permission:registrar_pagos');
});
