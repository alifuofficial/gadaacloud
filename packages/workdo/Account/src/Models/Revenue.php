<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantScoped;

class Revenue extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'revenue_number',
        'revenue_date',
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
            'revenue_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(RevenueCategories::class);
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

        static::creating(function ($revenue) {
            if (empty($revenue->revenue_number)) {
                $revenue->revenue_number = static::generateRevenueNumber($revenue->created_by);
            }
        });
    }

    public static function generateRevenueNumber($createdBy = null): string
    {
        $year = date('Y');
        $month = date('m');
        $userId = $createdBy ?: (auth()->check() ? creatorId() : 1);

        return AccountUtility::generateSequentialCode(static::class, 'revenue_number', "REV-{$year}-{$month}-", $userId);
    }
}
