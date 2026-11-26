<?php

namespace Workdo\Taskly\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class ProjectFile extends Model
{
    use TenantScoped;

    protected $fillable = [
        'project_id',
        'file_name',
        'file_path',
        'created_by',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}