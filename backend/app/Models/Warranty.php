<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty extends Model
{
    protected $fillable = [
        'title',
        'description',
        'duration_months',
        'full_text',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Duracion en lenguaje natural para el bloque legal del PDF.
     *
     * Los multiplos exactos de 12 se dicen en anos ("1 ano", "3 anos"); el
     * resto en meses. Imprimir "36 meses" donde el cliente vendio "3 anos"
     * confunde, aunque sea el mismo plazo.
     */
    public static function durationLabelFor(?int $months): string
    {
        $months = $months ?: 6;

        if ($months % 12 !== 0) {
            return $months.' '.($months === 1 ? 'mes' : 'meses');
        }

        $years = intdiv($months, 12);

        return $years.' '.($years === 1 ? 'año' : 'años');
    }

    public function durationLabel(): string
    {
        return self::durationLabelFor($this->duration_months);
    }
}
