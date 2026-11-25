<?php

namespace Workdo\Taskly\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectBoq extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'project_id',
        'item_no',
        'description',
        'unit',
        'quantity',
        'rate',
        'amount',
        'actual_quantity',
        'actual_amount',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'actual_quantity' => 'decimal:2',
            'actual_amount' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
