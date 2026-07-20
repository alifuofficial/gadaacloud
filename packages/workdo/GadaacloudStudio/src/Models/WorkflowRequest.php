<?php

namespace Workdo\GadaacloudStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\TenantScoped;
use App\Models\User;
use Carbon\Carbon;

class WorkflowRequest extends Model
{
    use TenantScoped;

    protected $fillable = [
        'workflow_id',
        'model_id',
        'current_step_id',
        'status',
        'priority',
        'deadline_at',
        'delegated_to_user_id',
        'submitter_note',
        'created_by',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(WorkflowDelegation::class);
    }

    public function delegatedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_user_id');
    }

    /**
     * Get the document model instance associated with this request
     */
    public function getDocumentAttribute()
    {
        $targetModel = $this->workflow->target_model ?? null;
        if ($targetModel && class_exists($targetModel)) {
            return $targetModel::find($this->model_id);
        }
        return null;
    }

    /**
     * Check if this request is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending'
            && $this->deadline_at !== null
            && $this->deadline_at->isPast();
    }

    /**
     * Days remaining until deadline (negative if overdue).
     */
    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (!$this->deadline_at) return null;
        return (int) now()->diffInDays($this->deadline_at, false);
    }

    /**
     * Priority sort weight for ordering (urgent=1, high=2, medium=3, low=4).
     */
    public static function priorityWeight(string $priority): int
    {
        return match ($priority) {
            'urgent' => 1,
            'high'   => 2,
            'medium' => 3,
            'low'    => 4,
            default  => 3,
        };
    }
}
