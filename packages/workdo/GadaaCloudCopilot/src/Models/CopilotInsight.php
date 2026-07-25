<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotInsight extends Model
{
    protected $table = 'copilot_insights';

    protected $fillable = [
        'created_by',
        'type',
        'title',
        'description',
        'metrics_data',
        'confidence_score',
        'status',
    ];

    protected $casts = [
        'metrics_data' => 'array',
        'confidence_score' => 'float',
    ];
}
