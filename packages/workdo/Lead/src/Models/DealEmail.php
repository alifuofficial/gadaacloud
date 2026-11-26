<?php

namespace Workdo\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class DealEmail extends Model
{
    use TenantScoped;

    protected $fillable = [
        'deal_id',
        'to',
        'subject',
        'description',
        'created_by',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}