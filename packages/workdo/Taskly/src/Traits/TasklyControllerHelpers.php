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
}
