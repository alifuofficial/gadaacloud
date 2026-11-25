<?php

namespace Workdo\DoubleEntry\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait DoubleEntryControllerHelpers
{
    /**
     * Create an exists rule strictly scoped to current tenant (creatorId()).
     */
    public static function tenantExists(string $table, string $column = 'id'): Exists
    {
        $creatorId = function_exists('creatorId') ? creatorId() : 1;
        return Rule::exists($table, $column)->where(function ($query) use ($creatorId) {
            $query->where('created_by', $creatorId);
        });
    }

    /**
     * Apply allowlisted safe sorting to query builder.
     */
    public function applySafeSort(Builder $query, array $columnMap, string $defaultSort = 'latest'): Builder
    {
        $sortParam = request('sort');
        $direction = strtolower(request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortParam && isset($columnMap[$sortParam])) {
            return $query->orderBy($columnMap[$sortParam], $direction);
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
     * Enforce safe pagination per_page bounds.
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
     * Assert model belongs strictly to the authenticated tenant workspace.
     */
    public function assertBelongsToTenant($model, string $errorMessage = 'Unauthorized access to resource.'): void
    {
        $creatorId = function_exists('creatorId') ? creatorId() : 1;
        if (!$model || (int) ($model->created_by ?? 0) !== (int) $creatorId) {
            abort(403, __($errorMessage));
        }
    }
}
