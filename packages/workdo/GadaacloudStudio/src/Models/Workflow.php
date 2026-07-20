<?php

namespace Workdo\GadaacloudStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\TenantScoped;

class Workflow extends Model
{
    use TenantScoped;

    protected $fillable = [
        'name',
        'target_model',
        'description',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sequence', 'asc');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class);
    }
}
