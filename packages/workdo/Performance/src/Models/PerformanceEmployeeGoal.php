<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class PerformanceEmployeeGoal extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'performance_employee_goals';

    protected $fillable = [
        'employee_id',
        'department_id',
        'goal_type_id',
        'review_cycle_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'target',
        'target_numeric',
        'current_value',
        'unit',
        'progress',
        'weight',
        'status',
        'kpi_source',
        'auto_sync_enabled',
        'last_synced_at',
        'creator_id',
        'created_by',
    ];

    protected $appends = ['progress_percentage'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'decimal:2',
            'target_numeric' => 'decimal:2',
            'current_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'auto_sync_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Extract a finite numeric value from a free-text goal target safely under PHP 8+.
     */
    public static function parseNumericTarget(?string $target): ?float
    {
        if ($target === null) {
            return null;
        }
        $normalized = str_replace([',', ' '], '', trim($target));
        if ($normalized === '') {
            return null;
        }
        if (is_numeric($normalized)) {
            return (float) $normalized;
        }
        if (preg_match('/[-+]?\d+(?:\.\d+)?/', $normalized, $matches)) {
            return (float) $matches[0];
        }
        return null;
    }

    /**
     * Compute safe percentage progress [0..100] without throwing PHP 8 TypeError on non-numeric targets.
     */
    public function getProgressPercentageAttribute(): float
    {
        $numericTarget = $this->target_numeric !== null
            ? (float) $this->target_numeric
            : self::parseNumericTarget($this->target);

        if ($this->current_value !== null && $numericTarget !== null && $numericTarget > 0) {
            return round(min(100, max(0, ((float) $this->current_value / $numericTarget) * 100)), 1);
        }

        // Fall back safely to percentage progress field (clamped 0..100)
        $rawProgress = (float) ($this->progress ?? 0);
        return round(min(100, max(0, $rawProgress)), 1);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function department()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Department::class, 'department_id');
    }

    public function goalType()
    {
        return $this->belongsTo(PerformanceGoalType::class, 'goal_type_id');
    }

    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
