<?php

namespace Workdo\Account\Models;

use App\Models\User;
use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use TenantScoped;

    protected $fillable = [
        'name',
        'fiscal_year',
        'period_number',
        'start_date',
        'end_date',
        'status',
        'locked_by',
        'locked_at',
        'notes',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['soft_locked', 'closed'], true);
    }

    public function scopeCoveringDate(Builder $query, string $date, int $companyId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where('created_by', $companyId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
