<?php

if (file_exists('C:/xampp/htdocs/facturaPro/backend/vendor/autoload.php')) {
    require 'C:/xampp/htdocs/facturaPro/backend/vendor/autoload.php';
    $app = require_once 'C:/xampp/htdocs/facturaPro/backend/bootstrap/app.php';
} else {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
}

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;
use Illuminate\Support\Facades\DB;

echo "=== REGISTRANDO LOGO CALDERAS BARCELONA PRO ===" . PHP_EOL;

DB::transaction(function () {
    foreach (FiscalProfile::all() as $profile) {
        $logo = FiscalProfileLogo::updateOrCreate(
            [
                'fiscal_profile_id' => $profile->id,
                'path' => 'logos/logo_calderas_barcelona_pro.png',
            ],
            [
                'label' => 'Calderas Barcelona PRO',
                'is_default' => false,
            ]
        );
        echo "Perfil [{$profile->id}] {$profile->name} -> Asociado Logo ID {$logo->id} ({$logo->label})" . PHP_EOL;
    }
});

echo PHP_EOL . "=== LOGOS ACTIVOS EN BASE DE DATOS ===" . PHP_EOL;
foreach (FiscalProfileLogo::with('fiscalProfile')->get() as $logo) {
    echo "ID: {$logo->id} | Perfil: {$logo->fiscal_profile_id} ({$logo->fiscalProfile?->name}) | Path: {$logo->path} | Label: {$logo->label} | Default: " . ($logo->is_default ? 'SI' : 'NO') . PHP_EOL;
}

echo "=== COMPLETADO ===" . PHP_EOL;
