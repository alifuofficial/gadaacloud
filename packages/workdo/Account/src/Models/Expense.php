<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantScoped;
use Workdo\Account\Helpers\AccountUtility;

class Expense extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'expense_number',
        'expense_date',
        'category_id',
        'bank_account_id',
        'chart_of_account_id',
        'amount',
        'description',
        'reference_number',
        'status',
        'approved_by',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategories::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber($expense->created_by);
            }
        });
    }

    public static function generateExpenseNumber($createdBy = null): string
    {
        $year = date('Y');
        $month = date('m');
        $userId = $createdBy ?: (auth()->check() ? creatorId() : 1);

        return AccountUtility::generateSequentialCode(static::class, 'expense_number', "EXP-{$year}-{$month}-", $userId);
    }
}