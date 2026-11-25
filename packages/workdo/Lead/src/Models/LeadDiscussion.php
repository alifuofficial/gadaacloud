<?php

namespace Workdo\Lead\Models;

use App\Models\User;
use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadDiscussion extends Model
{
    use TenantScoped;
    protected $fillable = [
        'lead_id',
        'comment',
        'creator_id',
        'created_by'
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}