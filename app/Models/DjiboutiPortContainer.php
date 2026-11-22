<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;
use Carbon\Carbon;

class DjiboutiPortContainer extends Model
{
    use TenantScoped;

    protected $table = 'djibouti_port_containers';

    protected $fillable = [
        'created_by',
        'container_number',
        'vessel_name',
        'bill_of_lading',
        'discharge_date',
        'free_storage_days',
        'daily_demurrage_usd',
        'current_location',
        'status',
        'cleared_date',
        'notes',
    ];

    protected $casts = [
        'discharge_date'      => 'date',
        'cleared_date'        => 'date',
        'free_storage_days'   => 'integer',
        'daily_demurrage_usd' => 'decimal:2',
    ];

    /**
     * Compute current days in port
     */
    public function getDaysInPortAttribute(): int
    {
        if (!$this->discharge_date) return 0;
        $endDate = $this->cleared_date ? Carbon::parse($this->cleared_date) : now();
        return max(0, $endDate->diffInDays(Carbon::parse($this->discharge_date)));
    }

    /**
     * Compute overdue demurrage days
     */
    public function getOverdueDaysAttribute(): int
    {
        $daysInPort = $this->days_in_port;
        return max(0, $daysInPort - ($this->free_storage_days ?: 8));
    }

    /**
     * Compute total accrued demurrage penalty in USD
     */
    public function getDemurragePenaltyUsdAttribute(): float
    {
        if ($this->status !== 'in_port') return 0.0;
        return round($this->overdue_days * floatval($this->daily_demurrage_usd ?: 50.00), 2);
    }
}
