<?php

namespace Workdo\SupportTicket\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

trait SupportTicketControllerHelpers
{
    /**
     * Tenant-scoped exists rule — only validates IDs owned by the current company (or explicit companyId).
     */
    public static function tenantExists(string $table, string $column = 'id', ?int $companyId = null): Exists
    {
        return \Illuminate\Validation\Rule::exists($table, $column)
            ->where('created_by', $companyId ?? creatorId());
    }

    /**
     * Clamp the per_page query param to a safe range.
     */
    public function safePerPage(int $default = 10, int $max = 100): int
    {
        $requested = (int) request('per_page', $default);
        if ($requested <= 0) {
            return $default;
        }
        return min($requested, $max);
    }

    /**
     * Returns true when the model belongs to the current tenant, false otherwise.
     */
    public function assertBelongsToTenant($model): bool
    {
        return isset($model->created_by) && (int) $model->created_by === (int) creatorId();
    }

    /**
     * Apply a sort direction that is restricted to asc/desc only.
     */
    public function applySafeSort(Builder $query, string $column, ?string $direction = null): Builder
    {
        $dir = strtolower((string) ($direction ?? request('direction', 'asc')));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }
        return $query->orderBy($column, $dir);
    }
}
