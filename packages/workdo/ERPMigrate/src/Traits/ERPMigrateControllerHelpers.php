<?php

namespace Workdo\ERPMigrate\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ERPMigrateControllerHelpers
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

    public function applySafeSort(Builder $query, array $allowedColumns, ?string $sort = null, ?string $direction = null, string $defaultColumn = 'id', string $defaultDirection = 'desc'): Builder
    {
        $col = $sort ?? request('sort');
        if (!is_string($col) || !in_array($col, $allowedColumns, true)) {
            return $query->orderBy($defaultColumn, $defaultDirection);
        }
        $dir = strtolower((string) ($direction ?? request('direction', 'asc')));
        return $query->orderBy($col, in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc');
    }

    public static function isValidConnectorHost(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower(trim($parsed['host'], '[]'));
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254', 'metadata.google.internal'], true)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return true;
    }
}
