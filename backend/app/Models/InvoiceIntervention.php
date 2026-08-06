<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class InvoiceIntervention extends Model
{
    protected $fillable = [
        'invoice_id',
        'equipment_type',
        'equipment_brand',
        'equipment_model',
        'equipment_serial',
        'equipment_location',
        'units_indoor',
        'units_outdoor',
        'diagnostic_summary',
        'technical_conclusions',
        'service_scope',
        'included_items',
    ];

    protected function casts(): array
    {
        return [
            'units_indoor' => 'integer',
            'units_outdoor' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * True when there is at least one equipment field filled in.
     *
     * Lets the PDF template hide the whole "equipo" card instead of printing a
     * grid of dashes.
     */
    public function hasEquipmentData(): bool
    {
        return filled($this->equipment_type)
            || filled($this->equipment_brand)
            || filled($this->equipment_model)
            || filled($this->equipment_serial)
            || filled($this->equipment_location);
    }

    /**
     * The "INCLUYE" checklist, one entry per non-empty line.
     *
     * @return Collection<int, string>
     */
    public function includedItemsList(): Collection
    {
        return collect(preg_split('/\R/', (string) $this->included_items) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->values();
    }
}
