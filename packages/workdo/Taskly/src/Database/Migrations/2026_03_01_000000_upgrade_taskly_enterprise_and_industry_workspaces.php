<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade projects table
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'currency_code')) {
                $table->string('currency_code', 10)->default('ETB')->after('budget');
            }
            if (!Schema::hasColumn('projects', 'project_type')) {
                $table->enum('project_type', ['general', 'construction', 'manufacturing', 'import_export', 'consulting'])->default('general')->after('name');
            }
            if (!Schema::hasColumn('projects', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->index()->after('creator_id');
            }
            if (!Schema::hasColumn('projects', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->index()->after('branch_id');
            }
            if (!Schema::hasColumn('projects', 'cost_center_id')) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->index()->after('department_id');
            }
            if (!Schema::hasColumn('projects', 'estimated_hrs')) {
                $table->decimal('estimated_hrs', 10, 2)->default(0)->after('budget');
            }
            if (!Schema::hasColumn('projects', 'share_token')) {
                $table->string('share_token', 64)->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('projects', 'is_client_visible')) {
                $table->boolean('is_client_visible')->default(true)->after('share_token');
            }
        });

        // 2. Upgrade project_tasks table
        Schema::table('project_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('project_tasks', 'start_date')) {
                $table->date('start_date')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('project_tasks', 'due_date')) {
                $table->date('due_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('project_tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->default(0)->after('due_date');
            }
            if (!Schema::hasColumn('project_tasks', 'progress')) {
                $table->integer('progress')->default(0)->after('estimated_hours');
            }
            if (!Schema::hasColumn('project_tasks', 'predecessor_id')) {
                $table->unsignedBigInteger('predecessor_id')->nullable()->index()->after('stage_id');
            }
            if (!Schema::hasColumn('project_tasks', 'dependency_type')) {
                $table->enum('dependency_type', ['finish_to_start', 'start_to_start', 'finish_to_finish'])->default('finish_to_start')->after('predecessor_id');
            }
            if (!Schema::hasColumn('project_tasks', 'is_critical')) {
                $table->boolean('is_critical')->default(false)->after('dependency_type');
            }
        });

        // 3. Upgrade project_milestones table for Stage-Gate Approvals
        Schema::table('project_milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('project_milestones', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->index()->after('progress');
            }
            if (!Schema::hasColumn('project_milestones', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('project_milestones', 'approval_status')) {
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('approved_at');
            }
            if (!Schema::hasColumn('project_milestones', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }
        });

        // 4. Create task_timesheets table
        if (!Schema::hasTable('task_timesheets')) {
            Schema::create('task_timesheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
                $table->unsignedBigInteger('task_id')->nullable()->index();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->date('date');
                $table->decimal('hours', 8, 2);
                $table->boolean('is_billable')->default(true);
                $table->decimal('hourly_rate', 10, 2)->default(0);
                $table->text('note')->nullable();
                $table->enum('status', ['draft', 'submitted', 'approved', 'invoiced'])->default('draft');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->foreign('task_id')->references('id')->on('project_tasks')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 5. Create project_boqs table (Construction BoQ)
        if (!Schema::hasTable('project_boqs')) {
            Schema::create('project_boqs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('item_no', 50);
                $table->text('description');
                $table->string('unit', 20)->default('pcs');
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('rate', 12, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('actual_quantity', 12, 2)->default(0);
                $table->decimal('actual_amount', 14, 2)->default(0);
                $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 6. Create project_site_diaries table (Construction Daily Site Log)
        if (!Schema::hasTable('project_site_diaries')) {
            Schema::create('project_site_diaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
                $table->date('date');
                $table->string('weather', 100)->default('Normal');
                $table->integer('workforce_count')->default(0);
                $table->decimal('machinery_hours', 8, 2)->default(0);
                $table->text('work_completed');
                $table->text('challenges')->nullable();
                $table->foreignId('logged_by')->constrained('users')->onDelete('cascade');
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 7. Create project_logistics table (Import/Export Logistics Tracking)
        if (!Schema::hasTable('project_logistics')) {
            Schema::create('project_logistics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('lc_number', 100)->nullable();
                $table->string('nbe_permit_no', 100)->nullable();
                $table->string('bl_number', 100)->nullable();
                $table->integer('container_count')->default(1);
                $table->text('container_numbers')->nullable();
                $table->string('port_of_loading', 100)->nullable();
                $table->string('discharge_port', 100)->default('Djibouti Port');
                $table->string('dry_port', 100)->default('Modjo Dry Port');
                $table->enum('current_stage', [
                    'forex_approved',
                    'lc_opened',
                    'ocean_transit',
                    'djibouti_discharge',
                    'modjo_customs',
                    'delivered'
                ])->default('forex_approved');
                $table->integer('port_free_days')->default(14);
                $table->date('discharge_date')->nullable();
                $table->date('demurrage_start_date')->nullable();
                $table->decimal('demurrage_daily_cost', 10, 2)->default(0);
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_logistics');
        Schema::dropIfExists('project_site_diaries');
        Schema::dropIfExists('project_boqs');
        Schema::dropIfExists('task_timesheets');

        Schema::table('project_milestones', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at', 'approval_status', 'rejection_reason']);
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'due_date', 'estimated_hours', 'progress', 'predecessor_id', 'dependency_type', 'is_critical']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'project_type', 'branch_id', 'department_id', 'cost_center_id', 'estimated_hrs', 'share_token', 'is_client_visible']);
        });
    }
};
