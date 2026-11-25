<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\TenantScoped;
use Workdo\Account\Helpers\AccountUtility;

class BankTransfer extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'from_account_id',
        'to_account_id',
        'transfer_amount',
        'transfer_charges',
        'reference_number',
        'description',
        'status',
        'journal_entry_id',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'transfer_amount' => 'decimal:2',
        'transfer_charges' => 'decimal:2'
    ];

    public function fromAccount()
    {
        return $this->belongsTo(BankAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(BankAccount::class, 'to_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'creator_id');
    }

    public static function generateTransferNumber()
    {
        $year = date('Y');
        $month = date('m');

        return AccountUtility::generateSequentialCode(static::class, 'transfer_number', "BT-{$year}-{$month}-", null, true);
    }

    public function getTotalDebitAttribute()
    {
        return $this->transfer_amount + $this->transfer_charges;
    }
}