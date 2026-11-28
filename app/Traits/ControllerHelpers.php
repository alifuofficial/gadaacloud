<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ControllerHelpers
{
    /**
     * Tenant-scoped exists rule — validates IDs owned by the current company (creatorId()).
     */
    public static function tenantExists(string $table, string $column = 'id', ?int $companyId = null): Exists
    {
        return Rule::exists($table, $column)
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
     * Apply an allowlisted sort column and sanitized direction (asc/desc).
     */
    public function applySafeSort(
        Builder $query,
        array $allowedColumns,
        ?string $sort = null,
        ?string $direction = null,
        string $defaultColumn = 'created_at',
        string $defaultDirection = 'desc'
    ): Builder {
        $col = $sort ?? request('sort');
        if (!is_string($col) || !in_array($col, $allowedColumns, true)) {
            return $query->orderBy($defaultColumn, $defaultDirection);
        }

        $dir = strtolower((string) ($direction ?? request('direction', 'asc')));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        return $query->orderBy($col, $dir);
    }

    /**
     * Sanitize untrusted rich-text HTML consistently across controllers.
     */
    protected function sanitizeRichText(?string $html): ?string
    {
        return \App\Support\HtmlSanitizer::sanitize($html);
    }

    /**
     * Extract clean plain text from stored rich-text HTML for previews and exports.
     */
    protected function richTextToPlainText(?string $html, ?int $maxLength = null): string
    {
        return \App\Support\HtmlSanitizer::toPlainText($html, $maxLength);
    }
}
