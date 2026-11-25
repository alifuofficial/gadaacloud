<?php

namespace Workdo\Taskly\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectLogistics extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'project_id',
        'lc_number',
        'nbe_permit_no',
        'bl_number',
        'container_count',
        'container_numbers',
        'port_of_loading',
        'discharge_port',
        'dry_port',
        'current_stage',
        'port_free_days',
        'discharge_date',
        'demurrage_start_date',
        'demurrage_daily_cost',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discharge_date' => 'date',
            'demurrage_start_date' => 'date',
            'demurrage_daily_cost' => 'decimal:2',
            'container_count' => 'integer',
            'port_free_days' => 'integer',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getDemurrageDaysAttribute(): int
    {
        if (!$this->demurrage_start_date) return 0;
        $now = now()->startOfDay();
        $start = $this->demurrage_start_date->startOfDay();
        return $now->greaterThan($start) ? $start->diffInDays($now) : 0;
    }

    public function getTotalDemurrageRiskAttribute(): float
    {
        return (float) ($this->demurrage_days * $this->demurrage_daily_cost);
    }
}
