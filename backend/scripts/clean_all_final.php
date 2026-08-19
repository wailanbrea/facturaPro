<?php

require 'C:/xampp/htdocs/facturaPro/backend/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/facturaPro/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceIntervention;
use App\Models\InvoicePayment;
use App\Models\TechnicalReport;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Models\InvoiceNumberSetting;
use App\Models\TechnicalReportNumberSetting;
use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "=== INICIANDO LIMPIEZA TOTAL Y REINICIO DE NUMERACIÓN ===" . PHP_EOL;

// 1. Crear respaldo preventivo
$backupDir = storage_path('app/backups');
File::ensureDirectoryExists($backupDir);
$backupFile = $backupDir . '/backup_pre_final_cleanup_' . date('Ymd_His') . '.json';

$backupData = [
    'timestamp' => now()->toIso8601String(),
    'invoices' => Invoice::with(['items', 'intervention', 'payments'])->get()->toArray(),
    'technical_reports' => TechnicalReport::all()->toArray(),
    'appointments' => Appointment::all()->toArray(),
    'clients' => Client::all()->toArray(),
    'activity_logs' => ActivityLog::all()->toArray(),
    'invoice_sequences' => InvoiceNumberSetting::all()->toArray(),
    'report_sequences' => TechnicalReportNumberSetting::all()->toArray(),
    'logos' => FiscalProfileLogo::all()->toArray(),
];

file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Respaldo preventivo guardado en: {$backupFile}" . PHP_EOL;

// 2. Ejecutar limpieza dentro de transacción
DB::transaction(function () {
    // A. Vaciado de tablas transaccionales
    $invoiceCount = Invoice::count();
    $reportCount = TechnicalReport::count();
    $clientCount = Client::count();
    $appointmentCount = Appointment::count();
    $logCount = ActivityLog::count();

    // Eliminar dependencias de facturas
    InvoicePayment::query()->delete();
    InvoiceItem::query()->delete();
    InvoiceIntervention::query()->delete();
    Invoice::query()->delete();

    // Eliminar informes técnicos
    TechnicalReport::query()->delete();

    // Eliminar citas
    Appointment::query()->delete();
    if (DB::getSchemaBuilder()->hasTable('appointment_contacts')) {
        DB::table('appointment_contacts')->delete();
    }

    // Eliminar clientes
    Client::query()->delete();

    // Eliminar logs de actividad
    ActivityLog::query()->delete();

    echo "Eliminadas {$invoiceCount} facturas/presupuestos, {$reportCount} informes técnicos, {$clientCount} clientes, {$appointmentCount} citas, {$logCount} logs." . PHP_EOL;

    // B. Reiniciar secuencias de numeración a 1
    // Eliminar secuencias duplicadas/huérfanas y dejar exactamente 1 por perfil fiscal
    InvoiceNumberSetting::query()->delete();
    TechnicalReportNumberSetting::query()->delete();

    foreach (FiscalProfile::all() as $profile) {
        $prefix = $profile->id === 3 ? 'A' : ($profile->id === 4 ? 'B' : 'A');
        
        InvoiceNumberSetting::create([
            'fiscal_profile_id' => $profile->id,
            'document_type' => 'invoice',
            'prefix' => 'FAC-',
            'serie' => $prefix,
            'next_number' => 1,
            'number_length' => 6,
        ]);

        InvoiceNumberSetting::create([
            'fiscal_profile_id' => $profile->id,
            'document_type' => 'quotation',
            'prefix' => 'PRE-',
            'serie' => $prefix,
            'next_number' => 1,
            'number_length' => 6,
        ]);

        TechnicalReportNumberSetting::create([
            'fiscal_profile_id' => $profile->id,
            'prefix' => 'INF-',
            'serie' => $prefix,
            'next_number' => 1,
            'number_length' => 6,
        ]);

        // Asegurar que el perfil fiscal use el logo oficial
        $profile->update([
            'logo_path' => 'logos/logo_tu_tecnico_autorizado.png',
        ]);
    }
    echo "Secuencias de numeración de Facturas y de Informes Técnicos reiniciadas a 1." . PHP_EOL;

    // C. Limpiar logos antiguos/negros, dejando solo el logo oficial
    $deletedLogos = FiscalProfileLogo::where('path', '!=', 'logos/logo_tu_tecnico_autorizado.png')->delete();
    echo "Eliminados {$deletedLogos} logos antiguos/secundarios." . PHP_EOL;

    // Asegurar que cada perfil tenga registrado el logo oficial en fiscal_profile_logos
    foreach (FiscalProfile::all() as $profile) {
        FiscalProfileLogo::firstOrCreate(
            [
                'fiscal_profile_id' => $profile->id,
                'path' => 'logos/logo_tu_tecnico_autorizado.png',
            ],
            [
                'label' => 'Tu Técnico Autorizado',
                'is_default' => true,
            ]
        );
    }
    echo "Logos oficiales verificados y activos." . PHP_EOL;
});

echo PHP_EOL . "=== RESUMEN POST-LIMPIEZA ===" . PHP_EOL;
echo "Facturas: " . Invoice::count() . PHP_EOL;
echo "Informes: " . TechnicalReport::count() . PHP_EOL;
echo "Clientes: " . Client::count() . PHP_EOL;
echo "Citas: " . Appointment::count() . PHP_EOL;
echo "Usuarios activos: " . App\Models\User::count() . PHP_EOL;
echo "Perfiles fiscales: " . FiscalProfile::count() . PHP_EOL;
echo "Cuentas bancarias: " . App\Models\BankAccount::count() . PHP_EOL;
echo "Logos activos: " . FiscalProfileLogo::count() . PHP_EOL;

foreach (FiscalProfileLogo::all() as $logo) {
    echo " -> Logo ID {$logo->id} | Perfil {$logo->fiscal_profile_id} | Path: {$logo->path} | Label: {$logo->label}" . PHP_EOL;
}
foreach (InvoiceNumberSetting::all() as $ins) {
    echo " -> Secuencia Factura ID {$ins->id} | Perfil {$ins->fiscal_profile_id} | Tipo: {$ins->document_type} | Prefijo: {$ins->prefix}{$ins->serie} | Next: {$ins->next_number}" . PHP_EOL;
}
foreach (TechnicalReportNumberSetting::all() as $tns) {
    echo " -> Secuencia Informe ID {$tns->id} | Perfil {$tns->fiscal_profile_id} | Next: {$tns->next_number}" . PHP_EOL;
}

echo "=== LIMPIEZA FINALIZADA CON ÉXITO ===" . PHP_EOL;
