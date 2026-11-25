<?php

namespace Workdo\DoubleEntry\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComparativeBalanceSheet extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'current_period_id',
        'previous_period_id',
        'comparison_date',
        'ethiopian_fiscal_year',
        'notes',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'comparison_date' => 'date',
    ];

    public function currentPeriod()
    {
        return $this->belongsTo(BalanceSheet::class, 'current_period_id');
    }

    public function previousPeriod()
    {
        return $this->belongsTo(BalanceSheet::class, 'previous_period_id');
    }
}
