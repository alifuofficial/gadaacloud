<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class NbeForexQueue extends Model
{
    use TenantScoped;

    protected $table = 'nbe_forex_queues';

    protected $fillable = [
        'created_by',
        'lc_number',
        'bank_name',
        'nbe_queue_number',
        'is_franco_valuta',
        'amount_usd',
        'queue_status',
        'application_date',
        'allocated_date',
        'notes',
    ];

    protected $casts = [
        'is_franco_valuta' => 'boolean',
        'amount_usd'       => 'decimal:2',
        'application_date' => 'date',
        'allocated_date'   => 'date',
    ];
}
