<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\TenantScoped;

class TradeLc extends Model
{
    use TenantScoped;

    protected $table = 'trade_lcs';

    protected $fillable = [
        'created_by',
        'lc_number',
        'purchase_order_id',
        'vendor_id',
        'issuing_bank',
        'advising_bank',
        'amount',
        'currency',
        'exchange_rate',
        'tolerance_percent',
        'payment_terms',
        'latest_shipment_date',
        'expiry_date',
        'status',
        'creator_id'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'tolerance_percent' => 'decimal:2',
        'latest_shipment_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_order_id');
    }

    public function vendor(): BelongsTo
    {
        // Links to User model with role 'vendor' or 'company'
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(TradeShipment::class, 'lc_id');
    }

    public function landedCosts(): HasMany
    {
        return $this->hasMany(TradeLandedCost::class, 'lc_id');
    }
}
