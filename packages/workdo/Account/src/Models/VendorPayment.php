<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Traits\TenantScoped;
use Workdo\Account\Helpers\AccountUtility;

class VendorPayment extends Model
{
    use TenantScoped;

    protected $fillable = [
        'payment_number',
        'payment_date',
        'vendor_id',
        'bank_account_id',
        'reference_number',
        'payment_amount',
        'wht_rate',
        'wht_amount',
        'gross_settled_amount',
        'wht_certificate_number',
        'currency_code',
        'exchange_rate',
        'foreign_amount',
        'fx_gain_loss',
        'status',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'wht_rate' => 'decimal:2',
        'wht_amount' => 'decimal:2',
        'gross_settled_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'foreign_amount' => 'decimal:2',
        'fx_gain_loss' => 'decimal:2',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'payment_id');
    }

    public function debitNoteApplications(): HasMany
    {
        return $this->hasMany(DebitNoteApplication::class, 'payment_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = static::generatePaymentNumber();
            }
        });
    }

    public static function generatePaymentNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        return AccountUtility::generateSequentialCode(static::class, 'payment_number', "VP-{$year}-{$month}-");
    }
}