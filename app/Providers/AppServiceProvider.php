<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Auto-log stock changes to inventory audit ledger using explicit InventoryAuditContext
        if (class_exists('Workdo\ProductService\Models\WarehouseStock')) {
            \Workdo\ProductService\Models\WarehouseStock::updated(function ($stock) {
                $ctx = \App\Services\InventoryAuditContext::current();
                if ($ctx && ($ctx['suppress'] ?? false)) {
                    return;
                }

                $original = floatval($stock->getOriginal('quantity'));
                $current = floatval($stock->quantity);
                $delta = $current - $original;

                if ($delta != 0 && !request()->is('*adjustment*')) {
                    $companyId = $stock->warehouse?->created_by
                        ?? ($stock->product ? $stock->product->created_by : (function_exists('creatorId') ? creatorId() : 1));

                    \App\Models\InventoryAuditLog::create([
                        'created_by'     => $companyId,
                        'warehouse_id'   => $stock->warehouse_id,
                        'product_id'     => $stock->product_id,
                        'action_type'    => $ctx['action_type'] ?? (request()->is('*transfer*') ? 'transfer' : (request()->is('*purchase*') ? 'purchase' : 'sale')),
                        'reference_type' => $ctx['reference_type'] ?? null,
                        'reference_id'   => $ctx['reference_id'] ?? null,
                        'qty_delta'      => $delta,
                        'final_qty'      => $current,
                        'notes'          => $ctx['notes'] ?? null,
                        'creator_id'     => \Illuminate\Support\Facades\Auth::id() ?? $companyId,
                    ]);
                }
            });

            \Workdo\ProductService\Models\WarehouseStock::created(function ($stock) {
                $ctx = \App\Services\InventoryAuditContext::current();
                if ($ctx && ($ctx['suppress'] ?? false)) {
                    return;
                }

                $delta = floatval($stock->quantity);

                if ($delta > 0 && !request()->is('*adjustment*')) {
                    $companyId = $stock->warehouse?->created_by
                        ?? ($stock->product ? $stock->product->created_by : (function_exists('creatorId') ? creatorId() : 1));

                    \App\Models\InventoryAuditLog::create([
                        'created_by'     => $companyId,
                        'warehouse_id'   => $stock->warehouse_id,
                        'product_id'     => $stock->product_id,
                        'action_type'    => $ctx['action_type'] ?? (request()->is('*transfer*') ? 'transfer' : (request()->is('*purchase*') ? 'purchase' : 'initial_stock')),
                        'reference_type' => $ctx['reference_type'] ?? null,
                        'reference_id'   => $ctx['reference_id'] ?? null,
                        'qty_delta'      => $delta,
                        'final_qty'      => $delta,
                        'notes'          => $ctx['notes'] ?? null,
                        'creator_id'     => \Illuminate\Support\Facades\Auth::id() ?? $companyId,
                    ]);
                }
            });
        }
    }
}
