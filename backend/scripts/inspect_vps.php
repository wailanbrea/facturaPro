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

echo "=== ESTADO VPS ===" . PHP_EOL;
echo "Facturas / Presupuestos: " . Invoice::count() . PHP_EOL;
echo "Items de factura: " . InvoiceItem::count() . PHP_EOL;
echo "Intervenciones: " . InvoiceIntervention::count() . PHP_EOL;
echo "Pagos: " . InvoicePayment::count() . PHP_EOL;
echo "Informes técnicos: " . TechnicalReport::count() . PHP_EOL;
echo "Citas: " . Appointment::count() . PHP_EOL;
echo "Clientes: " . Client::count() . PHP_EOL;
echo "Activity logs: " . ActivityLog::count() . PHP_EOL;

echo PHP_EOL . "=== LOGOS VPS ===" . PHP_EOL;
foreach (FiscalProfileLogo::all() as $logo) {
    echo "ID: {$logo->id} | Perfil ID: {$logo->fiscal_profile_id} | Path: {$logo->path} | Label: {$logo->label}" . PHP_EOL;
}

echo PHP_EOL . "=== PERFILES FISCALES VPS ===" . PHP_EOL;
foreach (FiscalProfile::all() as $fp) {
    echo "Perfil ID: {$fp->id} | Nombre: {$fp->name} | Logo Path: {$fp->logo_path}" . PHP_EOL;
}

echo PHP_EOL . "=== SECUENCIAS FACTURAS VPS ===" . PHP_EOL;
foreach (InvoiceNumberSetting::all() as $ins) {
    echo "ID: {$ins->id} | Perfil: {$ins->fiscal_profile_id} | Next Inv: {$ins->next_invoice_number} | Next Quo: {$ins->next_quotation_number}" . PHP_EOL;
}

echo PHP_EOL . "=== SECUENCIAS INFORMES VPS ===" . PHP_EOL;
foreach (TechnicalReportNumberSetting::all() as $tns) {
    echo "ID: {$tns->id} | Perfil: {$tns->fiscal_profile_id} | Next: {$tns->next_number}" . PHP_EOL;
}
