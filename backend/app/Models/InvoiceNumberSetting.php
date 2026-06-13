<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberSetting extends Model
{
    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    protected $fillable = [
        'fiscal_profile_id',
        'user_id',
        'document_type',
        'prefix',
        'next_number',
        'number_length',
        'serie',
        'reset_yearly',
        'reset_monthly',
        'allow_manual_number',
        'current_year',
        'current_month',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'number_length' => 'integer',
            'reset_yearly' => 'boolean',
            'reset_monthly' => 'boolean',
            'allow_manual_number' => 'boolean',
            'current_year' => 'integer',
            'current_month' => 'integer',
        ];
    }
}
