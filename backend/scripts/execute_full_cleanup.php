<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

echo "=== INICIANDO PROCESO DE LIMPIEZA DE DATOS ===" . PHP_EOL;

// 1. Crear backup en formato JSON/dump en storage
$backupDir = storage_path('app/backups');
if (!File::isDirectory($backupDir)) {
    File::makeDirectory($backupDir, 0755, true, true);
}
$backupFile = $backupDir . '/backup_pre_cleanup_' . date('Y_m_d_His') . '.json';

$backupData = [];
$allTables = [
    'invoices', 'invoice_items', 'invoice_interventions', 'invoice_payments',
    'technical_reports', 'technical_report_sections', 'technical_report_photos',
    'appointments', 'appointment_contacts', 'activity_logs', 'clients'
];

foreach ($allTables as $tbl) {
    if (Schema::hasTable($tbl)) {
        $backupData[$tbl] = DB::table($tbl)->get()->toArray();
    }
}
file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "[OK] Respaldo de seguridad guardado en: " . $backupFile . PHP_EOL;

// 2. Ejecutar limpieza
DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

$tablesToTruncate = [
    'invoice_payments',
    'invoice_items',
    'invoice_interventions',
    'invoices',
    'technical_report_photos',
    'technical_report_sections',
    'technical_reports',
    'appointment_contacts',
    'appointments',
    'activity_logs',
    'clients',
    'invoice_number_sequences',
    'technical_report_number_sequences',
];

foreach ($tablesToTruncate as $table) {
    if (Schema::hasTable($table)) {
        $countBefore = DB::table($table)->count();
        DB::table($table)->truncate();
        echo "[LIMPIADO] Tabla '{$table}': {$countBefore} registros eliminados." . PHP_EOL;
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

// 3. Limpiar PDFs temporales en storage
$storageInvoicePdfs = storage_path('app/invoices');
if (File::isDirectory($storageInvoicePdfs)) {
    File::cleanDirectory($storageInvoicePdfs);
    echo "[OK] PDFs cacheados en storage/app/invoices limpiados." . PHP_EOL;
}

$storageReportPdfs = storage_path('app/reports');
if (File::isDirectory($storageReportPdfs)) {
    File::cleanDirectory($storageReportPdfs);
    echo "[OK] PDFs cacheados en storage/app/reports limpiados." . PHP_EOL;
}

echo "=== LIMPIEZA FINALIZADA CON ÉXITO ===" . PHP_EOL;
