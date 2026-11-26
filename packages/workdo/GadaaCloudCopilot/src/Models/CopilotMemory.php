<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class CopilotMemory extends Model
{
    use HasFactory, TenantScoped;
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
