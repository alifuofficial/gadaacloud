<?php

namespace Workdo\Taskly\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'project_type',
        'description',
        'budget',
        'currency_code',
        'estimated_hrs',
        'start_date',
        'end_date',
        'status',
        'share_token',
        'is_client_visible',
        'creator_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'created_by',
    ];

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'project_users');
    }

    public function clients()
    {
        return $this->belongsToMany(User::class, 'project_clients', 'project_id', 'client_id');
    }

    public function milestones()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectMilestone::class);
    }

    public function tasks()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectTask::class);
    }

    public function bugs()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectBug::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ActivityLog::class);
    }

    public function files()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectFile::class);
    }

    public function timesheets()
    {
        return $this->hasMany(\Workdo\Taskly\Models\TaskTimesheet::class);
    }

    public function boqs()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectBoq::class);
    }

    public function siteDiaries()
    {
        return $this->hasMany(\Workdo\Taskly\Models\ProjectSiteDiary::class);
    }

    public function logistics()
    {
        return $this->hasOne(\Workdo\Taskly\Models\ProjectLogistics::class);
    }

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'estimated_hrs' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_client_visible' => 'boolean',
        ];
    }

    /**
     * Ensure a secure unique share token exists for client magic link tracking.
     */
    public function ensureShareToken(): string
    {
        if (empty($this->share_token)) {
            $this->share_token = Str::random(40);
            $this->saveQuietly();
        }
        return $this->share_token;
    }

    /**
     * Compute Earned Value Management (EVM) and Financial Actuals.
     */
    public function getEvmMetrics(): array
    {
        $bac = (float) ($this->budget ?? 0); // Budget at Completion

        // Calculate labor actual cost from timesheets
        $laborActual = (float) $this->timesheets()
            ->selectRaw('SUM(hours * hourly_rate) as total_cost')
            ->value('total_cost') ?? 0;

        // Calculate material actual cost if BoQ exists
        $boqActual = (float) $this->boqs()->sum('actual_amount');

        // Total Actual Cost (AC)
        $actualCost = $laborActual + $boqActual;

        // Calculate completion progress (%) from tasks
        $totalTasks = $this->tasks()->count();
        if ($totalTasks > 0) {
            $completedTasks = $this->tasks()->whereHas('stage', function ($q) {
                $q->where('name', 'like', '%done%')
                  ->orWhere('name', 'like', '%finish%')
                  ->orWhere('name', 'like', '%complete%');
            })->count();
            $progressPercent = round(($completedTasks / $totalTasks) * 100);
        } else {
            $progressPercent = $this->status === 'Finished' ? 100 : 0;
        }

        // Earned Value (EV) = BAC * % Complete
        $earnedValue = round($bac * ($progressPercent / 100), 2);

        // Planned Value (PV) based on elapsed time vs total duration
        $plannedValue = $bac;
        if ($this->start_date && $this->end_date && $this->end_date->greaterThan($this->start_date)) {
            $totalDays = max(1, $this->start_date->diffInDays($this->end_date));
            $now = now()->startOfDay();
            if ($now->lessThanOrEqualTo($this->start_date)) {
                $plannedPercent = 0;
            } elseif ($now->greaterThanOrEqualTo($this->end_date)) {
                $plannedPercent = 100;
            } else {
                $elapsedDays = $this->start_date->diffInDays($now);
                $plannedPercent = min(100, round(($elapsedDays / $totalDays) * 100));
            }
            $plannedValue = round($bac * ($plannedPercent / 100), 2);
        }

        // Cost Variance (CV) = EV - AC
        $costVariance = round($earnedValue - $actualCost, 2);

        // Schedule Variance (SV) = EV - PV
        $scheduleVariance = round($earnedValue - $plannedValue, 2);

        // Cost Performance Index (CPI) = EV / AC
        $cpi = $actualCost > 0 ? round($earnedValue / $actualCost, 2) : 1.0;

        // Schedule Performance Index (SPI) = EV / PV
        $spi = $plannedValue > 0 ? round($earnedValue / $plannedValue, 2) : 1.0;

        return [
            'budget' => $bac,
            'currency' => $this->currency_code ?? 'ETB',
            'labor_actual' => $laborActual,
            'material_actual' => $boqActual,
            'actual_cost' => $actualCost,
            'planned_value' => $plannedValue,
            'earned_value' => $earnedValue,
            'progress_percent' => $progressPercent,
            'cost_variance' => $costVariance,
            'schedule_variance' => $scheduleVariance,
            'cpi' => $cpi,
            'spi' => $spi,
            'is_over_budget' => $actualCost > $bac && $bac > 0,
            'is_behind_schedule' => $scheduleVariance < 0,
        ];
    }

    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $staff_permission = [
            'manage-project',
            'view-project',
            'manage-own-project',
            'manage-project-task',
            'manage-own-project-task',
            'manage-project-bug',
            'manage-own-project-bug',
            'manage-project-timesheet',
            'manage-project-boq',
            'manage-project-logistics'
        ];

        $client_permission = [
            'manage-project',
            'view-project',
            'manage-own-project',
            'manage-project-task',
            'manage-own-project-task',
            'manage-project-bug',
            'manage-own-project-bug'
        ];

        if ($rolename == 'staff') {
            $roles_v = Role::where('name', 'staff')->where('id', $role_id)->first();
            if ($roles_v) {
                foreach ($staff_permission as $permission_v) {
                    $permission = Permission::firstOrCreate(['name' => $permission_v, 'guard_name' => 'web']);
                    if (!$roles_v->hasPermissionTo($permission_v)) {
                        $roles_v->givePermissionTo($permission);
                    }
                }
            }
        }

        if ($rolename == 'client') {
            $roles_v = Role::where('name', 'client')->where('id', $role_id)->first();
            if ($roles_v) {
                foreach ($client_permission as $permission_v) {
                    $permission = Permission::firstOrCreate(['name' => $permission_v, 'guard_name' => 'web']);
                    if (!$roles_v->hasPermissionTo($permission_v)) {
                        $roles_v->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}
