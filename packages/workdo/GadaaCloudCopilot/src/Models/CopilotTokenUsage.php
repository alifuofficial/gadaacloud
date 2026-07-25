<?php

namespace Workdo\GadaaCloudCopilot\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotTokenUsage extends Model
{
    protected $table = 'copilot_token_usages';

    protected $fillable = [
        'company_id',
        'user_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'token_cost',
        'model_name',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'token_cost' => 'float',
    ];
}
