<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class CopilotLearning extends Model
{
    use HasFactory, TenantScoped;
    protected $table = 'copilot_learnings';

    protected $fillable = [
        'created_by',
        'category', // 'user_habit', 'business_baseline', 'anomaly_threshold', 'learned_rule'
        'key',
        'value',
        'confidence_score',
        'metadata',
    ];

    protected $casts = [
        'value'            => 'array',
        'metadata'         => 'array',
        'confidence_score' => 'float',
    ];
}
