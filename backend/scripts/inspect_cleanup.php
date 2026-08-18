<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== ESTADO ACTUAL DE LA BASE DE DATOS ===" . PHP_EOL;

$tablesToCheck = [
    'invoices' => 'Facturas y Presupuestos',
    'invoice_items' => 'Líneas de facturas / presupuestos',
    'invoice_interventions' => 'Intervenciones técnicas de facturas',
    'invoice_payments' => 'Pagos registrados',
    'technical_reports' => 'Informes técnicos',
    'appointments' => 'Citas de calendario',
    'appointment_contacts' => 'Contactos de citas',
    'activity_logs' => 'Registros de auditoría',
    'clients' => 'Clientes registrados',
];

foreach ($tablesToCheck as $table => $desc) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo sprintf("%-45s : %d registros\n", $desc . " ({$table})", $count);
    }
}

echo "==========================================" . PHP_EOL;
