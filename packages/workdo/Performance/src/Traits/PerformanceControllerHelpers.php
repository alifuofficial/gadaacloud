<?php

namespace Workdo\Performance\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared tenant-scoped validation and safe sorting helpers for Performance controllers.
 */
trait PerformanceControllerHelpers
{
    /**
     * Build a tenant-scoped database exists rule.
     */
    protected static function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where(function ($query) use ($table) {
            $query->where("{$table}.created_by", creatorId());
        });
    }

    /**
     * Apply strictly allowlisted sorting to a query builder to prevent SQL injection or 500s.
     *
     * @param  array<string, string|callable>  $columnMap  Maps request sort key -> DB column or closure
     */
    protected function applySafeSort(
        Builder $query,
        array $columnMap,
        string $defaultSort = 'latest'
    ): Builder {
        $sort = request('sort');
        $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (is_string($sort) && array_key_exists($sort, $columnMap)) {
            $target = $columnMap[$sort];
            if (is_callable($target)) {
                return $target($query, $direction);
            }
            return $query->orderBy($target, $direction);
        }

        return $defaultSort === 'latest'
            ? $query->latest()
            : $query->orderBy($defaultSort, 'desc');
    }

    /**
     * Clamp pagination page size cleanly.
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
     * Safely decode JSON or array rating maps without double-encoding artifacts.
     *
     * @return array<int|string, float|int>
     */
    public static function decodeRatingMap(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        // Handle legacy double-encoded JSON strings from older conductStore() versions
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
