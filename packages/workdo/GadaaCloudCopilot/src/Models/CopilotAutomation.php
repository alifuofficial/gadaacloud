<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotAutomation extends Model
{
    protected $table = 'copilot_automations';

    protected $fillable = [
        'created_by',
        'name',
        'trigger_event',
        'action_type',
        'is_active',
        'last_run_at',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'config' => 'array',
    ];
}
