<?php

namespace Workdo\Taskly\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait TasklyControllerHelpers
{
    /**
     * Create an exists rule strictly scoped to the current tenant (creatorId()).
     */
    public static function tenantExists(string $table, string $column = 'id'): Exists
    {
        $creatorId = function_exists('creatorId') ? creatorId() : 1;
        return Rule::exists($table, $column)->where(function ($query) use ($creatorId) {
            $query->where('created_by', $creatorId);
        });
    }

    /**
     * Create an exists rule for child tables scoped via project_id belonging to current tenant.
     */
    public static function projectChildExists(string $table, string $column = 'id'): Exists
    {
        $creatorId = function_exists('creatorId') ? creatorId() : 1;
        return Rule::exists($table, $column)->where(function ($query) use ($creatorId) {
            $query->whereIn('project_id', function ($sub) use ($creatorId) {
                $sub->select('id')->from('projects')->where('created_by', $creatorId);
            });
        });
    }

    /**
     * Apply allowlisted or sanitized safe sorting to query builder.
     */
    public function applySafeSort(Builder $query, array $columnMap = [], string $defaultSort = 'latest'): Builder
    {
        $sortParam = request('sort');
        $direction = strtolower(request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortParam) {
            if (!empty($columnMap) && isset($columnMap[$sortParam])) {
                return $query->orderBy($columnMap[$sortParam], $direction);
            }
            if (empty($columnMap) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]{0,49}$/', $sortParam)) {
                return $query->orderBy($sortParam, $direction);
            }
        }

        if ($defaultSort === 'latest') {
            return $query->latest();
        }

        if (isset($columnMap[$defaultSort])) {
            return $query->orderBy($columnMap[$defaultSort], 'desc');
        }

        return $query->latest();
    }

    /**
     * Enforce safe pagination bounds.
     */
    public function safePerPage(int $default = 10, int $max = 100): int
    {
        $perPage = (int) request('per_page', $default);
        if ($perPage <= 0) {
            return $default;
        }
        return min($perPage, $max);
    }

    /**
     * Verify a model belongs strictly to the authenticated tenant workspace.
     */
    public function assertBelongsToTenant($model): bool
    {
        $creatorId = function_exists('creatorId') ? creatorId() : 1;
        return $model && (int) ($model->created_by ?? 0) === (int) $creatorId;
    }

    /**
     * Columns to select from users when hydrating Taskly members/assignees cleanly.
     */
    protected function userSelectColumns(string $prefix = ''): array
    {
        $cols = ['id', 'name', 'email', 'avatar', 'is_disable', 'is_enable_login'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'deactivation_reason')) {
            $cols[] = 'deactivation_reason';
        }

        if ($prefix === '') {
            return $cols;
        }

        return array_map(fn (string $col) => $prefix . '.' . $col, $cols);
    }

    /**
     * Format a user model for Taskly views/APIs including lifecycle status flags.
     */
    protected function formatProjectUserSummary($user, array $extra = []): array
    {
        return array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? null,
            'avatar' => $user->avatar ?? null,
            'is_disable' => (int) ($user->is_disable ?? 0),
            'is_enable_login' => (bool) ($user->is_enable_login ?? true),
            'deactivation_reason' => $user->deactivation_reason ?? null,
            'is_deactivated' => (bool) ($user->is_deactivated ?? ((int) ($user->is_disable ?? 0) === 1 || !($user->is_enable_login ?? true))),
            'account_status_label' => $user->account_status_label ?? (((int) ($user->is_disable ?? 0) === 1 || !($user->is_enable_login ?? true)) ? 'Deactivated' : 'Active'),
        ], $extra);
    }

    /**
     * Parse assigned_to column safely whether JSON array or CSV string.
     */
    protected function parseAssignedIds($assignedTo): array
    {
        if (empty($assignedTo)) {
            return [];
        }
        if (is_array($assignedTo)) {
            return array_values(array_filter(array_map('intval', $assignedTo)));
        }
        $decoded = json_decode((string) $assignedTo, true);
        $ids = is_array($decoded) ? $decoded : explode(',', (string) $assignedTo);

        return array_values(array_filter(array_map('intval', array_filter($ids, fn ($v) => $v !== ''))));
    }

    /**
     * Load assigned user summaries (preserving deactivated/resigned users with status badges).
     */
    protected function loadAssignedUsersSummary($assignedTo): \Illuminate\Support\Collection
    {
        $userIds = $this->parseAssignedIds($assignedTo);
        if (empty($userIds)) {
            return collect();
        }

        return \App\Models\User::whereIn('id', $userIds)
            ->select($this->userSelectColumns())
            ->get()
            ->map(fn ($user) => $this->formatProjectUserSummary($user))
            ->values();
    }

    /**
     * Load project team members with deactivation/separation flags for UI pickers & views.
     */
    protected function loadProjectTeamMembersSummary(?\Workdo\Taskly\Models\Project $project): \Illuminate\Support\Collection
    {
        if (!$project) {
            return collect();
        }

        return $project->teamMembers()
            ->select($this->userSelectColumns('users'))
            ->get()
            ->map(fn ($user) => $this->formatProjectUserSummary($user))
            ->values();
    }
}
