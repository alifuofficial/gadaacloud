<?php

namespace Workdo\GadaacloudStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class WorkflowDelegation extends Model
{
    protected $fillable = [
        'workflow_request_id',
        'workflow_step_id',
        'delegated_by_user_id',
        'delegated_to_user_id',
        'reason',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
        'is_active'   => 'boolean',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequest::class, 'workflow_request_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by_user_id');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_user_id');
    }

    /**
     * Check if this delegation is currently active and within valid date range.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) return false;
        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;
        return true;
    }
}
