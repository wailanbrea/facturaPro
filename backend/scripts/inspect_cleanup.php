<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== ESTADO DE DOCUMENTOS Y DATOS TRANSACCIONALES ===" . PHP_EOL;

$tablesToCheck = [
    'invoices' => 'Facturas y Presupuestos',
    'invoice_items' => 'Líneas de facturas / presupuestos',
    'invoice_interventions' => 'Intervenciones técnicas de facturas',
    'invoice_payments' => 'Pagos registrados',
    'technical_reports' => 'Informes técnicos',
    'appointments' => 'Citas de calendario',
    'activity_logs' => 'Registros de auditoría',
    'clients' => 'Clientes registrados',
];

foreach ($tablesToCheck as $table => $desc) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo sprintf("%-50s : %d registros\n", $desc . " ({$table})", $count);
    }
}

echo PHP_EOL . "=== CONFIGURACIÓN Y CATÁLOGOS BASE (INTACTOS) ===" . PHP_EOL;
$catalogs = [
    'users' => 'Usuarios y Técnicos',
    'fiscal_profiles' => 'Perfiles Fiscales',
    'fiscal_profile_logos' => 'Logos autorizados',
    'bank_accounts' => 'Cuentas bancarias',
    'currencies' => 'Monedas',
    'taxes' => 'Impuestos',
    'payment_terms' => 'Términos de pago',
    'warranties' => 'Garantías',
    'settings' => 'Configuración general',
];

foreach ($catalogs as $table => $desc) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo sprintf("%-50s : %d registros\n", $desc . " ({$table})", $count);
    }
}

echo "===================================================" . PHP_EOL;
