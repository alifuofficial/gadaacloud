<?php

namespace Workdo\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class LeadFile extends Model
{
    use TenantScoped;

    protected $fillable = [
        'lead_id',
        'file_name',
        'file_path',
        'created_by'
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}