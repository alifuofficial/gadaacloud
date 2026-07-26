<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotPrediction extends Model
{
    protected $table = 'copilot_predictions';

    protected $fillable = [
        'created_by',
        'type', // 'cashflow', 'stockout', 'deal_win', 'customer_churn', 'tax_due'
        'target_model',
        'target_id',
        'title',
        'prediction_summary',
        'predicted_value',
        'probability', // 0.00 to 1.00 (e.g. 0.85 = 85% probability)
        'impact_level', // 'low', 'medium', 'high', 'critical'
        'recommended_action',
        'metadata',
    ];

    protected $casts = [
        'predicted_value' => 'array',
        'probability'     => 'float',
        'metadata'        => 'array',
    ];
}
