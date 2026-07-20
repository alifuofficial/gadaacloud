<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class LoginHistory extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'ip',
        'date',
        'details',
        'type',
        'created_by'
    ];

    protected $casts = [
        'details' => 'array',
        'date' => 'date'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
