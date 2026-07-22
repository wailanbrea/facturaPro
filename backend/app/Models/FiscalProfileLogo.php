<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FiscalProfileLogo extends Model
{
    protected $appends = ['preview_url'];

    protected $fillable = [
        'fiscal_profile_id',
        'path',
        'label',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalProfile::class);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! is_string($this->path) || trim($this->path) === '') {
            return null;
        }

        return url(Storage::disk('public')->url($this->path));
    }
}
