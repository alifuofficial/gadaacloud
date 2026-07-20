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
        // Auto-log stock changes to inventory audit ledger
        if (class_exists('Workdo\ProductService\Models\WarehouseStock')) {
            \Workdo\ProductService\Models\WarehouseStock::updated(function ($stock) {
                $original = floatval($stock->getOriginal('quantity'));
                $current = floatval($stock->quantity);
                $delta = $current - $original;

                // Prevent double logs for adjustment requests since they create logs manually in controller
                if ($delta != 0 && !request()->is('*adjustment*')) {
                    \App\Models\InventoryAuditLog::create([
                        'created_by' => $stock->product ? $stock->product->created_by : creatorId(),
                        'warehouse_id' => $stock->warehouse_id,
                        'product_id' => $stock->product_id,
                        'action_type' => request()->is('*transfer*') ? 'transfer' : (request()->is('*purchase*') ? 'purchase' : 'sale'),
                        'reference_id' => null,
                        'qty_delta' => $delta,
                        'final_qty' => $current,
                        'creator_id' => \Illuminate\Support\Facades\Auth::id() ?? 1
                    ]);
                }
            });

            \Workdo\ProductService\Models\WarehouseStock::created(function ($stock) {
                $delta = floatval($stock->quantity);
                
                if ($delta > 0 && !request()->is('*adjustment*')) {
                    \App\Models\InventoryAuditLog::create([
                        'created_by' => $stock->product ? $stock->product->created_by : creatorId(),
                        'warehouse_id' => $stock->warehouse_id,
                        'product_id' => $stock->product_id,
                        'action_type' => request()->is('*transfer*') ? 'transfer' : (request()->is('*purchase*') ? 'purchase' : 'initial_stock'),
                        'reference_id' => null,
                        'qty_delta' => $delta,
                        'final_qty' => $delta,
                        'creator_id' => \Illuminate\Support\Facades\Auth::id() ?? 1
                    ]);
                }
            });
        }
    }
}
