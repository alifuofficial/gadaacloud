<?php

namespace Workdo\GadaacloudStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class WorkflowLog extends Model
{
    protected $fillable = [
        'workflow_request_id',
        'workflow_step_id',
        'user_id',
        'action',
        'comment'
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequest::class, 'workflow_request_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
