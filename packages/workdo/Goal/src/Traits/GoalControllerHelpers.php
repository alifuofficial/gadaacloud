<?php

namespace Workdo\Goal\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait GoalControllerHelpers
{
    /**
     * Create a tenant-scoped exists validation rule.
     */
    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where(function ($query) {
            $tenantId = creatorId();
            $query->where('created_by', $tenantId);
        });
    }

    /**
     * Apply allowlisted safe sorting to an Eloquent builder.
     */
    protected function applySafeSort(Builder $query, array $columnMap, string $defaultSort = 'latest'): Builder
    {
        $sort = request('sort');
        $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort && isset($columnMap[$sort])) {
            return $query->orderBy($columnMap[$sort], $direction);
        }

        if ($defaultSort === 'latest') {
            return $query->latest();
        }

        if (isset($columnMap[$defaultSort])) {
            return $query->orderBy($columnMap[$defaultSort], 'asc');
        }

        return $query->latest();
    }

    /**
     * Safely clamp per-page pagination parameter.
     */
    protected function safePerPage(int $default = 10, int $max = 100): int
    {
        $perPage = (int) request('per_page', $default);
        if ($perPage < 1) {
            return $default;
        }
        return min($perPage, $max);
    }

    /**
     * Verify that a given model belongs to the current tenant workspace.
     */
    protected function assertBelongsToTenant($model): bool
    {
        if (!$model) {
            return false;
        }

        $tenantId = creatorId();
        return (int) $model->created_by === (int) $tenantId;
    }
}
