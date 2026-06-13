<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalText extends Model
{
    protected $fillable = [
        'name',
        'legal_footer',
        'warranty_text',
        'conformity_text',
        'client_copy_text',
        'seller_copy_text',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
