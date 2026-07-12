<?php

use App\Http\Controllers\Web\AppointmentController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\InvoiceVerificationController;
use App\Http\Controllers\Web\ReportController as FinancialReportController;
use App\Http\Controllers\Web\ReportSettingController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SettingsCatalogController;
use App\Http\Controllers\Web\TechnicalReportController;
use App\Http\Controllers\Web\TechnicalReportPdfController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

// Public authenticity check. It must stay before /invoices/{invoice} so the
// "verify" segment is never captured as an invoice route-model binding.
Route::get('/invoices/verify', [InvoiceVerificationController::class, 'show'])->name('web.invoices.verify');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('web.logout');
    Route::get('/', DashboardController::class)->name('web.dashboard');

    Route::resource('clients', ClientController::class)
        ->except(['show'])
        ->middleware('permission:gestionar_clientes')
        ->names('web.clients');

    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:ver_factura')->name('web.invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('permission:crear_factura')->name('web.invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:crear_factura')->name('web.invoices.store');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('permission:editar_factura')->name('web.invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:editar_factura')->name('web.invoices.update');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->middleware('permission:ver_factura')->name('web.invoices.preview');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:ver_factura')->name('web.invoices.show');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('permission:emitir_factura')->name('web.invoices.issue');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:anular_factura')->name('web.invoices.cancel');
    Route::post('/invoices/{invoice}/generate-pdf', [InvoiceController::class, 'generatePdf'])->middleware('permission:descargar_pdf')->name('web.invoices.generate-pdf');
    Route::get('/invoices/{invoice}/download-pdf', [InvoiceController::class, 'downloadPdf'])->middleware('permission:descargar_pdf')->name('web.invoices.download-pdf');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware('permission:registrar_pagos')->name('web.invoices.mark-paid');
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'registerPayment'])->middleware('permission:registrar_pagos')->name('web.invoices.register-payment');
    Route::post('/invoices/{invoice}/convert', [InvoiceController::class, 'convertQuotation'])->middleware('permission:crear_factura')->name('web.invoices.convert');

    Route::post('/reports/preview-draft', [TechnicalReportPdfController::class, 'previewDraft'])
        ->middleware('permission:crear_informes')
        ->name('web.technical-reports.preview-draft');
    Route::get('/reports/{technicalReport}/preview', [TechnicalReportPdfController::class, 'preview'])
        ->middleware('permission:ver_informes')
        ->name('web.technical-reports.preview');
    Route::post('/reports/{technicalReport}/generate-pdf', [TechnicalReportPdfController::class, 'generate'])
        ->middleware('permission:descargar_informes')
        ->name('web.technical-reports.generate-pdf');
    Route::get('/reports/{technicalReport}/download-pdf', [TechnicalReportPdfController::class, 'download'])
        ->middleware('permission:descargar_informes')
        ->name('web.technical-reports.download-pdf');
    Route::get('/reports', [TechnicalReportController::class, 'index'])
        ->middleware('permission:ver_informes')
        ->name('web.technical-reports.index');
    Route::get('/reports/create', [TechnicalReportController::class, 'create'])
        ->middleware('permission:crear_informes')
        ->name('web.technical-reports.create');
    Route::post('/reports', [TechnicalReportController::class, 'store'])
        ->middleware('permission:crear_informes')
        ->name('web.technical-reports.store');
    Route::get('/reports/{technicalReport}', [TechnicalReportController::class, 'show'])
        ->middleware('permission:ver_informes')
        ->name('web.technical-reports.show');
    Route::get('/reports/{technicalReport}/edit', [TechnicalReportController::class, 'edit'])
        ->middleware('permission:editar_informes')
        ->name('web.technical-reports.edit');
    Route::put('/reports/{technicalReport}', [TechnicalReportController::class, 'update'])
        ->middleware('permission:editar_informes')
        ->name('web.technical-reports.update');
    Route::delete('/reports/{technicalReport}', [TechnicalReportController::class, 'destroy'])
        ->middleware('permission:eliminar_informes')
        ->name('web.technical-reports.destroy');

    Route::middleware('permission:configurar_sistema')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'index'])->name('web.settings.index');
        Route::get('/settings/reports', [ReportSettingController::class, 'edit'])->name('web.settings.reports.edit');
        Route::put('/settings/reports', [ReportSettingController::class, 'update'])->name('web.settings.reports.update');
        Route::get('/settings/locked-fields', [SettingsController::class, 'editLockedFields'])->name('web.settings.locked-fields.edit');
        Route::put('/settings/locked-fields', [SettingsController::class, 'updateLockedFields'])->name('web.settings.locked-fields.update');
        Route::get('/settings/{catalog}', [SettingsCatalogController::class, 'index'])->name('web.settings.catalog.index');
        Route::get('/settings/{catalog}/create', [SettingsCatalogController::class, 'create'])->name('web.settings.catalog.create');
        Route::post('/settings/{catalog}', [SettingsCatalogController::class, 'store'])->name('web.settings.catalog.store');
        Route::get('/settings/{catalog}/{id}/edit', [SettingsCatalogController::class, 'edit'])->name('web.settings.catalog.edit');
        Route::put('/settings/{catalog}/{id}', [SettingsCatalogController::class, 'update'])->name('web.settings.catalog.update');
        Route::delete('/settings/{catalog}/{id}', [SettingsCatalogController::class, 'destroy'])->name('web.settings.catalog.destroy');
    });

    Route::resource('users', UserController::class)
        ->except(['show', 'destroy'])
        ->middleware('permission:gestionar_usuarios')
        ->names('web.users');

    Route::middleware('permission:gestionar_usuarios')->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index'])->name('web.roles.index');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('web.roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('web.roles.update');
    });
    Route::get('/financial-reports', FinancialReportController::class)->middleware('permission:ver_reportes')->name('web.reports.index');

    Route::middleware('permission:ver_calendario')->group(function (): void {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('web.appointments.index');
        Route::get('/appointments/create', [AppointmentController::class, 'create'])->middleware('permission:gestionar_citas')->name('web.appointments.create');
        Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('permission:gestionar_citas')->name('web.appointments.store');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('web.appointments.show');
        Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->middleware('permission:gestionar_citas')->name('web.appointments.edit');
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:gestionar_citas')->name('web.appointments.update');
        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->middleware('permission:gestionar_citas')->name('web.appointments.status');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->middleware('permission:gestionar_citas')->name('web.appointments.destroy');
    });

    Route::get('/auditoria', [AuditLogController::class, 'index'])
        ->middleware('permission:ver_auditoria')
        ->name('web.audit.index');
});
