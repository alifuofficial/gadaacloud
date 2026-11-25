<?php

namespace Workdo\Taskly\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Taskly\Models\TaskStage;
use App\Models\User;

class ProjectTask extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'project_id',
        'milestone_id',
        'title',
        'priority',
        'assigned_to',
        'duration',
        'start_date',
        'due_date',
        'estimated_hours',
        'progress',
        'description',
        'stage_id',
        'predecessor_id',
        'dependency_type',
        'is_critical',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'estimated_hours' => 'decimal:2',
            'progress' => 'integer',
            'is_critical' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone()
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function taskStage()
    {
        return $this->belongsTo(TaskStage::class, 'stage_id');
    }

    public function stage()
    {
        return $this->belongsTo(TaskStage::class, 'stage_id');
    }

    public function predecessor()
    {
        return $this->belongsTo(ProjectTask::class, 'predecessor_id');
    }

    public function successors()
    {
        return $this->hasMany(ProjectTask::class, 'predecessor_id');
    }

    public function timesheets()
    {
        return $this->hasMany(TaskTimesheet::class, 'task_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedUsers()
    {
        $userIds = [];
        if (is_array($this->assigned_to)) {
            $userIds = $this->assigned_to;
        } elseif (is_string($this->assigned_to)) {
            $decoded = json_decode($this->assigned_to, true);
            $userIds = is_array($decoded) ? $decoded : explode(',', $this->assigned_to);
        }
        return User::whereIn('id', $userIds)->get();
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(TaskSubtask::class, 'task_id');
    }
}
