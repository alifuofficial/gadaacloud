<?php

namespace Workdo\EIMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class EimsOfflineQueue extends Model
{
    use TenantScoped;

    protected $table = 'eims_offline_queue';

    const STATUS_QUEUED = 'QUEUED';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_DEAD_LETTER = 'DEAD_LETTER';

    protected $fillable = [
        'created_by',
        'eims_invoice_id',
        'idempotency_key',
        'payload',
        'attempt_count',
        'max_attempts',
        'last_attempt_at',
        'next_attempt_at',
        'expires_at',
        'last_error',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    public function eimsInvoice(): BelongsTo
    {
        return $this->belongsTo(EimsInvoice::class, 'eims_invoice_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getHoursRemainingAttribute(): float
    {
        if (!$this->expires_at || $this->expires_at->isPast()) {
            return 0;
        }
        return round(now()->diffInMinutes($this->expires_at) / 60, 1);
    }
}
