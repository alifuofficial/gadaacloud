<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScoped
{
    public static function bootTenantScoped()
    {
        // Auto-stamp created_by on new records
        static::creating(function ($model) {
            if (Auth::check()) {
                if (empty($model->created_by)) {
                    $model->created_by = creatorId();
                }
                try {
                    if (\Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'creator_id')) {
                        if (empty($model->creator_id)) {
                            $model->creator_id = Auth::id();
                        }
                    }
                } catch (\Throwable $e) {
                    // Silently ignore schema issues if database is not ready or connected
                }
            }
        });

        // Apply per-request tenant scope using once() so the resolved tenant ID
        // is cached for the lifetime of one HTTP request / CLI command only.
        // This fixes the static-variable bug that would bleed tenant context
        // across requests in Octane or queue workers.
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Guard against recursive calls during early bootstrap
            // (e.g. Debugbar / exception handler queries before session is ready)
            static $isResolving = false;
            if ($isResolving) {
                return;
            }

            $isResolving = true;
            try {
                if (!Auth::check()) {
                    return;
                }

                // once() caches the result for the current request/process only.
                // Each new HTTP request gets a fresh resolution — safe for Octane.
                $isSuperAdmin = once(fn() => (bool) optional(Auth::user())->hasRole('superadmin'));

                if ($isSuperAdmin) {
                    return; // SuperAdmin sees all data
                }

                $tenantId = once(fn() => creatorId());

                if ($tenantId) {
                    $table = $builder->getQuery()->from;
                    $builder->where("{$table}.created_by", $tenantId);
                }
            } catch (\Throwable $e) {
                // Silently skip during early bootstrap (before session/auth is ready)
            } finally {
                $isResolving = false;
            }
        });
    }
}
