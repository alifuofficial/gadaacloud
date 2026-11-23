<?php

namespace Workdo\Taskly\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class ProjectSiteDiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date',
        'weather',
        'workforce_count',
        'machinery_hours',
        'work_completed',
        'challenges',
        'logged_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'machinery_hours' => 'decimal:2',
            'workforce_count' => 'integer',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
