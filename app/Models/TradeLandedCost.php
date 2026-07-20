<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;
use Workdo\Account\Models\JournalEntry;

class TradeLandedCost extends Model
{
    use TenantScoped;

    protected $table = 'trade_landed_costs';

    protected $fillable = [
        'created_by',
        'creator_id',
        'lc_id',
        'freight_charges',
        'insurance_fees',
        'custom_duties',
        'agent_fees',
        'bank_fees',
        'currency',
        'allocation_method',
        'is_posted_to_accounts',
        'journal_entry_id'
    ];

    protected $casts = [
        'freight_charges' => 'decimal:4',
        'insurance_fees' => 'decimal:4',
        'custom_duties' => 'decimal:4',
        'agent_fees' => 'decimal:4',
        'bank_fees' => 'decimal:4',
        'is_posted_to_accounts' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lc(): BelongsTo
    {
        return $this->belongsTo(TradeLc::class, 'lc_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the total sum of all landed cost overhead fees
     */
    public function getTotalCostAttribute(): float
    {
        return floatval($this->freight_charges) +
               floatval($this->insurance_fees) +
               floatval($this->custom_duties) +
               floatval($this->agent_fees) +
               floatval($this->bank_fees);
    }
}
