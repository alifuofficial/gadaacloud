<?php

namespace Workdo\GadaacloudStudio\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;

class GadaacloudStudioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load web routes
        $routesPath = __DIR__.'/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        // Load database migrations
        $migrationsPath = __DIR__.'/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        // Listen for new record creations to trigger workflows automatically across all modules
        Event::listen('eloquent.created: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if (!$model || !is_object($model)) return;

            $modelClass = get_class($model);

            // Prevent recursion/infinite loops on workflow tables
            if (str_starts_with($modelClass, 'Workdo\\GadaacloudStudio\\Models\\')) {
                return;
            }

            try {
                if (Schema::hasTable('workflows')) {
                    $companyId = null;
                    if (isset($model->created_by) && is_numeric($model->created_by)) {
                        $companyId = (int) $model->created_by;
                    } elseif (isset($model->company_id) && is_numeric($model->company_id)) {
                        $companyId = (int) $model->company_id;
                    } elseif (\Illuminate\Support\Facades\Auth::check()) {
                        $companyId = (int) creatorId();
                    }

                    if ($companyId) {
                        \Workdo\GadaacloudStudio\Services\WorkflowService::submit($model, $companyId);
                    }
                }
            } catch (\Throwable $e) {
                // Silently catch exceptions to prevent workflow faults from blocking core CRUD operations
            }
        });
    }

    public function register(): void
    {
        //
    }
}
