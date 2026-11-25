<?php

namespace Workdo\Goal\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalMilestone extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'goal_id',
        'milestone_name',
        'milestone_description',
        'target_amount',
        'target_date',
        'achieved_date',
        'achieved_amount',
        'status',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'achieved_amount' => 'decimal:2',
            'target_date' => 'date',
            'achieved_date' => 'date',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }
}
