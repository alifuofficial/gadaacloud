<?php

namespace Workdo\GadaacloudStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_id',
        'name',
        'approver_type',
        'approver_role',
        'approver_user_id',
        'sequence',
        // Condition fields for auto-skip logic
        'condition_field',
        'condition_operator',
        'condition_value',
        'skip_if_condition_fails',
    ];

    protected $casts = [
        'skip_if_condition_fails' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /**
     * Determine if this step has a condition configured.
     */
    public function hasCondition(): bool
    {
        return !empty($this->condition_field) && !empty($this->condition_operator) && $this->condition_value !== null;
    }

    /**
     * Human-readable label for the condition.
     */
    public function conditionLabel(): string
    {
        if (!$this->hasCondition()) {
            return 'No condition';
        }
        $ops = ['gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤', 'eq' => '=', 'neq' => '≠'];
        $op = $ops[$this->condition_operator] ?? $this->condition_operator;
        return "{$this->condition_field} {$op} {$this->condition_value}";
    }
}
