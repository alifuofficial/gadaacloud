<?php

namespace Workdo\Lead\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Lead\Models\Pipeline;

class LeadStage extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'order',
        'pipeline_id',
        'creator_id',
        'created_by',
    ];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }
}