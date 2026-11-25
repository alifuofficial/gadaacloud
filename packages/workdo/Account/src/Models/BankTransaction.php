<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Traits\TenantScoped;

class BankTransaction extends Model
{
    use TenantScoped;

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'transaction_type',
        'reference_number',
        'description',
        'amount',
        'running_balance',
        'transaction_status',
        'reconciliation_status',
        'matched_type',
        'matched_id',
        'reconciled_at',
        'reconciled_by',
        'batch_reference',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}