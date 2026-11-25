<?php

namespace Workdo\Performance\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    public function run(): void
    {
        $permission = [
            ['name' => 'manage-performance', 'module' => 'performance', 'label' => 'Manage Performance'],
            ['name' => 'manage-performance-dashboard', 'module' => 'performance', 'label' => 'Manage Performance Dashboard'],
            ['name' => 'manage-performance-system-setup', 'module' => 'performance', 'label' => 'Manage System Setup'],

            // Performance Indicator Categories
            ['name' => 'manage-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Manage Performance Indicator Categories'],
            ['name' => 'manage-any-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Manage All Performance Indicator Categories'],
            ['name' => 'manage-own-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Manage Own Performance Indicator Categories'],
            ['name' => 'create-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Create Performance Indicator Categories'],
            ['name' => 'edit-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Edit Performance Indicator Categories'],
            ['name' => 'delete-performance-indicator-categories', 'module' => 'performance-indicator-category', 'label' => 'Delete Performance Indicator Categories'],

            // Performance Indicators
            ['name' => 'manage-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Manage Performance Indicators'],
            ['name' => 'manage-any-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Manage All Performance Indicators'],
            ['name' => 'manage-own-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Manage Own Performance Indicators'],
            ['name' => 'view-performance-indicators', 'module' => 'performance-indicator', 'label' => 'View Performance Indicators'],
            ['name' => 'create-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Create Performance Indicators'],
            ['name' => 'edit-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Edit Performance Indicators'],
            ['name' => 'delete-performance-indicators', 'module' => 'performance-indicator', 'label' => 'Delete Performance Indicators'],

            // Goal Types
            ['name' => 'manage-goal-types', 'module' => 'goal-type', 'label' => 'Manage Goal Types'],
            ['name' => 'manage-any-goal-types', 'module' => 'goal-type', 'label' => 'Manage All Goal Types'],
            ['name' => 'manage-own-goal-types', 'module' => 'goal-type', 'label' => 'Manage Own Goal Types'],
            ['name' => 'create-goal-types', 'module' => 'goal-type', 'label' => 'Create Goal Types'],
            ['name' => 'edit-goal-types', 'module' => 'goal-type', 'label' => 'Edit Goal Types'],
            ['name' => 'delete-goal-types', 'module' => 'goal-type', 'label' => 'Delete Goal Types'],

            // Employee Goals
            ['name' => 'manage-employee-goals', 'module' => 'employee-goal', 'label' => 'Manage Employee Goals'],
            ['name' => 'manage-any-employee-goals', 'module' => 'employee-goal', 'label' => 'Manage All Employee Goals'],
            ['name' => 'manage-own-employee-goals', 'module' => 'employee-goal', 'label' => 'Manage Own Employee Goals'],
            ['name' => 'view-employee-goals', 'module' => 'employee-goal', 'label' => 'View Employee Goals'],
            ['name' => 'create-employee-goals', 'module' => 'employee-goal', 'label' => 'Create Employee Goals'],
            ['name' => 'edit-employee-goals', 'module' => 'employee-goal', 'label' => 'Edit Employee Goals'],
            ['name' => 'delete-employee-goals', 'module' => 'employee-goal', 'label' => 'Delete Employee Goals'],

            // Review Cycles
            ['name' => 'manage-review-cycles', 'module' => 'review-cycle', 'label' => 'Manage Review Cycles'],
            ['name' => 'manage-any-review-cycles', 'module' => 'review-cycle', 'label' => 'Manage All Review Cycles'],
            ['name' => 'manage-own-review-cycles', 'module' => 'review-cycle', 'label' => 'Manage Own Review Cycles'],
            ['name' => 'view-review-cycles', 'module' => 'review-cycle', 'label' => 'View Review Cycles'],
            ['name' => 'create-review-cycles', 'module' => 'review-cycle', 'label' => 'Create Review Cycles'],
            ['name' => 'edit-review-cycles', 'module' => 'review-cycle', 'label' => 'Edit Review Cycles'],
            ['name' => 'delete-review-cycles', 'module' => 'review-cycle', 'label' => 'Delete Review Cycles'],

            // Employee Reviews
            ['name' => 'manage-employee-reviews', 'module' => 'employee-review', 'label' => 'Manage Employee Reviews'],
            ['name' => 'manage-any-employee-reviews', 'module' => 'employee-review', 'label' => 'Manage All Employee Reviews'],
            ['name' => 'manage-own-employee-reviews', 'module' => 'employee-review', 'label' => 'Manage Own Employee Reviews'],
            ['name' => 'view-employee-reviews', 'module' => 'employee-review', 'label' => 'View Employee Reviews'],
            ['name' => 'create-employee-reviews', 'module' => 'employee-review', 'label' => 'Create Employee Reviews'],
            ['name' => 'edit-employee-reviews', 'module' => 'employee-review', 'label' => 'Edit Employee Reviews'],
            ['name' => 'delete-employee-reviews', 'module' => 'employee-review', 'label' => 'Delete Employee Reviews'],
            ['name' => 'conduct-employee-reviews', 'module' => 'employee-review', 'label' => 'Conduct Employee Reviews'],
            ['name' => 'calibrate-employee-reviews', 'module' => 'employee-review', 'label' => 'Calibrate & Lock Employee Reviews'],

            // Competency Categories & Competencies
            ['name' => 'manage-competency-categories', 'module' => 'competency-category', 'label' => 'Manage Competency Categories'],
            ['name' => 'create-competency-categories', 'module' => 'competency-category', 'label' => 'Create Competency Categories'],
            ['name' => 'edit-competency-categories', 'module' => 'competency-category', 'label' => 'Edit Competency Categories'],
            ['name' => 'delete-competency-categories', 'module' => 'competency-category', 'label' => 'Delete Competency Categories'],

            ['name' => 'manage-competencies', 'module' => 'competency', 'label' => 'Manage Competencies'],
            ['name' => 'create-competencies', 'module' => 'competency', 'label' => 'Create Competencies'],
            ['name' => 'edit-competencies', 'module' => 'competency', 'label' => 'Edit Competencies'],
            ['name' => 'delete-competencies', 'module' => 'competency', 'label' => 'Delete Competencies'],

            ['name' => 'manage-employee-competencies', 'module' => 'employee-competency', 'label' => 'Manage Employee Competencies'],
            ['name' => 'manage-any-employee-competencies', 'module' => 'employee-competency', 'label' => 'Manage All Employee Competencies'],
            ['name' => 'manage-own-employee-competencies', 'module' => 'employee-competency', 'label' => 'Manage Own Employee Competencies'],
            ['name' => 'create-employee-competencies', 'module' => 'employee-competency', 'label' => 'Assess Employee Competencies'],
            ['name' => 'edit-employee-competencies', 'module' => 'employee-competency', 'label' => 'Edit Employee Competencies'],
            ['name' => 'delete-employee-competencies', 'module' => 'employee-competency', 'label' => 'Delete Employee Competencies'],

            // 360 Feedback Types & Continuous Feedback
            ['name' => 'manage-feedback-types', 'module' => 'feedback-type', 'label' => 'Manage Feedback Types'],
            ['name' => 'create-feedback-types', 'module' => 'feedback-type', 'label' => 'Create Feedback Types'],
            ['name' => 'edit-feedback-types', 'module' => 'feedback-type', 'label' => 'Edit Feedback Types'],
            ['name' => 'delete-feedback-types', 'module' => 'feedback-type', 'label' => 'Delete Feedback Types'],

            ['name' => 'manage-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Manage 360 Feedback'],
            ['name' => 'manage-any-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Manage All 360 Feedback'],
            ['name' => 'manage-own-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Manage Own 360 Feedback'],
            ['name' => 'create-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Give 360 Feedback'],
            ['name' => 'edit-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Edit 360 Feedback'],
            ['name' => 'delete-employee-feedback', 'module' => 'employee-feedback', 'label' => 'Delete 360 Feedback'],

            // Review Templates & Performance Items
            ['name' => 'manage-review-templates', 'module' => 'review-template', 'label' => 'Manage Review Templates'],
            ['name' => 'create-review-templates', 'module' => 'review-template', 'label' => 'Create Review Templates'],
            ['name' => 'edit-review-templates', 'module' => 'review-template', 'label' => 'Edit Review Templates'],
            ['name' => 'delete-review-templates', 'module' => 'review-template', 'label' => 'Delete Review Templates'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'Performance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if ($company_role && !$company_role->hasPermissionTo($permission_obj)) {
                $company_role->givePermissionTo($permission_obj);
            }
        }
    }
}
