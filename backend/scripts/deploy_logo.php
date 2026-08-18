<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;

$src = __DIR__ . '/../public/images/logo_tu_tecnico_autorizado.png';
$logoDir = __DIR__ . '/../storage/app/public/logos';

if (! is_dir($logoDir)) {
    mkdir($logoDir, 0777, true);
}

if (is_file($src)) {
    copy($src, $logoDir . '/logo_tu_tecnico_autorizado.png');
    copy($src, $logoDir . '/tu_tecnico_autorizado.png');
    echo "Logo files placed in storage/app/public/logos successfully.\n";
}

FiscalProfile::query()->update(['logo_path' => 'logos/logo_tu_tecnico_autorizado.png']);
foreach (FiscalProfile::all() as $profile) {
    $profile->logos()->updateOrCreate(
        ['path' => 'logos/logo_tu_tecnico_autorizado.png'],
        [
            'label' => 'Tu Técnico Autorizado',
            'is_default' => true,
        ]
    );
}

echo "Database profiles updated: " . FiscalProfile::count() . " profile(s).\n";
