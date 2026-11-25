<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Traits\TenantScoped;
use Workdo\Account\Helpers\AccountUtility;

class JournalEntry extends Model
{
    use TenantScoped;

    protected $fillable = [
        'journal_number',
        'journal_date',
        'entry_type',
        'reference_type',
        'reference_id',
        'description',
        'total_debit',
        'total_credit',
        'currency_code',
        'exchange_rate',
        'status',
        'reversed_by_entry_id',
        'reversal_reason',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'journal_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function isBalanced(): bool
    {
        return $this->total_debit == $this->total_credit;
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($journalEntry) {
            if (empty($journalEntry->journal_number)) {
                $journalEntry->journal_number = static::generateJournalNumber();
            }
        });
    }

    public static function generateJournalNumber(): string
    {
        $year = date('Y');
        return AccountUtility::generateSequentialCode(static::class, 'journal_number', "JE-{$year}-", null, true);
    }
}