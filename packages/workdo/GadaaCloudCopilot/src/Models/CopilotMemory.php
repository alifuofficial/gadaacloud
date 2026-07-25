<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotMemory extends Model
{
    protected $table = 'copilot_memories';

    protected $fillable = [
        'user_id',
        'created_by',
        'session_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
