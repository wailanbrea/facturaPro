<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_URGENT = 'urgent';

    public const STATUS_PRIORITY = 'priority';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_URGENT,
        self::STATUS_PRIORITY,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_IN_PROGRESS => 'En curso',
        self::STATUS_DONE => 'Realizado',
        self::STATUS_URGENT => 'Urgente',
        self::STATUS_PRIORITY => 'Prioridad',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => '#3b82f6',
        self::STATUS_IN_PROGRESS => '#f59e0b',
        self::STATUS_DONE => '#10b981',
        self::STATUS_URGENT => '#ef4444',
        self::STATUS_PRIORITY => '#8b5cf6',
        self::STATUS_CANCELLED => '#9ca3af',
    ];

    protected $fillable = [
        'title',
        'client_id',
        'client_name',
        'created_by',
        'start_at',
        'end_at',
        'location',
        'location_lat',
        'location_lng',
        'contacts',
        'observations',
        'service_description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'contacts' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? '#9ca3af';
    }
}
