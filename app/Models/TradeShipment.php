<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class TradeShipment extends Model
{
    use TenantScoped;

    protected $table = 'trade_shipments';

    protected $fillable = [
        'created_by',
        'creator_id',
        'lc_id',
        'shipping_line',
        'vessel_name',
        'voyage_number',
        'container_numbers',
        'bill_of_lading',
        'etd',
        'eta',
        'atd',
        'ata',
        'status'
    ];

    protected $casts = [
        'etd' => 'date',
        'eta' => 'date',
        'atd' => 'date',
        'ata' => 'date',
        'container_numbers' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lc(): BelongsTo
    {
        return $this->belongsTo(TradeLc::class, 'lc_id');
    }
}
