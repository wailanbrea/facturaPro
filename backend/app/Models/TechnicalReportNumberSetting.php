<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalReportNumberSetting extends Model
{
    protected $fillable = ['fiscal_profile_id', 'prefix', 'serie', 'next_number', 'number_length'];

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'number_length' => 'integer'];
    }

    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalProfile::class);
    }
}
