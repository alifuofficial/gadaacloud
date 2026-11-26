<?php

namespace Workdo\Taskly\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantScoped;

class ProjectClient extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'project_id',
        'client_id',
        'created_by',
    ];

    protected $casts = [
        //
    ];
}
