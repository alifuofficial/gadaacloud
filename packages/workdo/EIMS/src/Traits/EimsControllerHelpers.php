<?php

namespace Workdo\EIMS\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait EimsControllerHelpers
{
    public static function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('created_by', creatorId());
    }

    public function safePerPage(int $default = 10, int $max = 100): int
    {
        $requested = (int) request('per_page', $default);
        return $requested <= 0 ? $default : min($requested, $max);
    }

    public function assertBelongsToTenant($model): bool
    {
        return isset($model->created_by) && (int) $model->created_by === (int) creatorId();
    }

    public function applySafeSort(Builder $query, array $allowedColumns, ?string $sort = null, ?string $direction = null, string $defaultColumn = 'created_at', string $defaultDirection = 'desc'): Builder
    {
        $col = $sort ?? request('sort');
        if (!is_string($col) || !in_array($col, $allowedColumns, true)) {
            return $query->orderBy($defaultColumn, $defaultDirection);
        }
        $dir = strtolower((string) ($direction ?? request('direction', 'asc')));
        return $query->orderBy($col, in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc');
    }
}
