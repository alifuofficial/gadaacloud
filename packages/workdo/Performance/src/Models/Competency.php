<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class Competency extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'competencies';

    protected $fillable = [
        'category_id',
        'department_id',
        'designation_id',
        'name',
        'description',
        'weight',
        'status',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(CompetencyCategory::class, 'category_id');
    }

    public function assessments()
    {
        return $this->hasMany(EmployeeCompetency::class, 'competency_id');
    }

    public function department()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Designation::class, 'designation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
