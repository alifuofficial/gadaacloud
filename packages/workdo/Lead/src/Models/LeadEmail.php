<?php

namespace Workdo\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class LeadEmail extends Model
{
    use TenantScoped;

    protected $fillable = [
        'lead_id',
        'to',
        'subject',
        'description',
        'created_by',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}