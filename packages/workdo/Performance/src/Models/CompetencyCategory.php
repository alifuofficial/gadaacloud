<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class CompetencyCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'competency_categories';

    protected $fillable = [
        'name',
        'description',
        'status',
        'creator_id',
        'created_by',
    ];

    public function competencies()
    {
        return $this->hasMany(Competency::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
