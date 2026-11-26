<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class CopilotAutomation extends Model
{
    use HasFactory, TenantScoped;
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
