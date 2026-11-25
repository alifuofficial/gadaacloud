<?php

namespace Workdo\Contract\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ContractControllerHelpers
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
     * Apply allowlisted safe sorting to query builder.
     */
    public function applySafeSort(Builder $query, array $columnMap = [], string $defaultSort = 'latest'): Builder
    {
        $sortParam = request('sort');
        $direction = strtolower(request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortParam && isset($columnMap[$sortParam])) {
            return $query->orderBy($columnMap[$sortParam], $direction);
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
     * Find a contract scoped to the current tenant or abort 403.
     */
    public function findTenantContract(int $id): \Workdo\Contract\Models\Contract
    {
        $contract = \Workdo\Contract\Models\Contract::where('created_by', function_exists('creatorId') ? creatorId() : 1)
            ->find($id);

        if (!$contract) {
            abort(404, __('Contract not found.'));
        }

        return $contract;
    }
}
