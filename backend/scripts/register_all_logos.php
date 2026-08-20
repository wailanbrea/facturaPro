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

echo "=== REGISTRANDO TODOS LOS LOGOS OFICIALES ===" . PHP_EOL;

$officialLogos = [
    [
        'path' => 'logos/logo_tu_tecnico_autorizado.png',
        'label' => 'Tu Técnico Autorizado',
        'is_default' => true,
    ],
    [
        'path' => 'logos/logo_calderas_barcelona_pro.png',
        'label' => 'Calderas Barcelona PRO',
        'is_default' => false,
    ],
    [
        'path' => 'logos/logo_aire_acondicionado_bcn_pro.png',
        'label' => 'Aire Acondicionado BCN PRO',
        'is_default' => false,
    ],
];

DB::transaction(function () use ($officialLogos) {
    // Eliminar logos no deseados
    $officialPaths = array_column($officialLogos, 'path');
    FiscalProfileLogo::whereNotIn('path', $officialPaths)->delete();

    foreach (FiscalProfile::all() as $profile) {
        foreach ($officialLogos as $logoData) {
            $logo = FiscalProfileLogo::updateOrCreate(
                [
                    'fiscal_profile_id' => $profile->id,
                    'path' => $logoData['path'],
                ],
                [
                    'label' => $logoData['label'],
                    'is_default' => $logoData['is_default'],
                ]
            );
            echo "Perfil [{$profile->id}] {$profile->name} -> Logo: {$logo->label} ({$logo->path})" . PHP_EOL;
        }

        // Asegurar que el perfil fiscal tenga como predeterminado Tu Técnico Autorizado
        if (blank($profile->logo_path) || !in_array($profile->logo_path, $officialPaths, true)) {
            $profile->update(['logo_path' => 'logos/logo_tu_tecnico_autorizado.png']);
        }
    }
});

echo PHP_EOL . "=== LOGOS DISPONIBLES EN EL SISTEMA ===" . PHP_EOL;
foreach (FiscalProfileLogo::with('fiscalProfile')->get() as $logo) {
    echo "ID: {$logo->id} | Perfil: {$logo->fiscal_profile_id} ({$logo->fiscalProfile?->name}) | Path: {$logo->path} | Label: {$logo->label} | Default: " . ($logo->is_default ? 'SI' : 'NO') . PHP_EOL;
}

echo "=== COMPLETADO CON ÉXITO ===" . PHP_EOL;
