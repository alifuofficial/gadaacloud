<?php

namespace Workdo\Hrm\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'title',
        'employee_id',
        'loan_type_id',
        'type',
        'amount',
        'paid_amount',
        'start_date',
        'end_date',
        'reason',
        'status',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function loanType()
    {
        return $this->belongsTo(LoanType::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }
}