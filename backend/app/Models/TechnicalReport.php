<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'report_number',
        'report_date',
        'fiscal_profile_id',
        'seller_name',
        'seller_tax_id',
        'seller_address',
        'seller_city',
        'seller_logo_path',
        'client_id',
        'recipient_name',
        'recipient_tax_id',
        'recipient_address',
        'section_1_title',
        'section_1_content',
        'section_2_title',
        'section_2_content',
        'section_3_title',
        'section_3_content',
        'section_4_title',
        'section_4_content',
        'intro_text',
        'final_text',
        'notes',
        'status',
        'pdf_path',
        'verification_code',
        'verification_hash',
        'previous_hash',
        'signed_at',
        'pdf_sha256',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
