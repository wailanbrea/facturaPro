<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AvailableLogos
{
    /**
     * Logos disponibles en storage/app/public/logos como pares [ruta => archivo].
     *
     * @return array<string, string>
     */
    public static function list(): array
    {
        $dir = Storage::disk('public')->path('logos');

        if (! is_dir($dir)) {
            return [];
        }

        $logos = [];
        foreach (File::files($dir) as $file) {
            if (in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], true)) {
                $relative = 'logos/'.$file->getFilename();
                $logos[$relative] = $file->getFilename();
            }
        }

        return $logos;
    }
}
