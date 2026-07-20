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

        // Listen for new record creations to trigger workflows automatically
        Event::listen('eloquent.created: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if (!$model) return;

            $modelClass = get_class($model);

            // Prevent recursion/infinite loops on workflow tables
            if (str_starts_with($modelClass, 'Workdo\\GadaacloudStudio\\Models\\')) {
                return;
            }

            try {
                if (Schema::hasTable('workflows')) {
                    // Check if there is an active workflow for this model class
                    // This query is naturally scoped by TenantScoped on Workflow model
                    $workflow = \Workdo\GadaacloudStudio\Models\Workflow::where('target_model', $modelClass)
                        ->where('is_active', true)
                        ->first();

                    if ($workflow) {
                        $firstStep = $workflow->steps()->orderBy('sequence', 'asc')->first();
                        if ($firstStep) {
                            \Workdo\GadaacloudStudio\Models\WorkflowRequest::create([
                                'workflow_id' => $workflow->id,
                                'model_id' => $model->id,
                                'current_step_id' => $firstStep->id,
                                'status' => 'pending',
                                'created_by' => $model->created_by ?? creatorId(),
                            ]);
                        }
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
