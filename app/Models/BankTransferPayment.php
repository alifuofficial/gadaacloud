<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class BankTransferPayment extends Model
{
    use TenantScoped;

    protected $fillable = [
        'order_id',
        'user_id',
        'request',
        'status',
        'type',
        'price',
        'price_currency',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        'request' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
