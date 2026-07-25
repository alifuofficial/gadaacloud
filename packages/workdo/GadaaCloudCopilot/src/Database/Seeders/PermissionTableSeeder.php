<?php

namespace Workdo\GadaaCloudCopilot\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['name' => 'manage-copilot', 'module' => 'copilot', 'label' => 'Manage GadaaCloud Copilot', 'add_on' => 'GadaaCloudCopilot'],
            ['name' => 'view-copilot-forecasts', 'module' => 'copilot', 'label' => 'View AI Cashflow Forecasts', 'add_on' => 'GadaaCloudCopilot'],
            ['name' => 'manage-copilot-tax', 'module' => 'copilot', 'label' => 'Manage AI Tax Calculations', 'add_on' => 'GadaaCloudCopilot'],
            ['name' => 'manage-copilot-automations', 'module' => 'copilot', 'label' => 'Manage Operational Automations', 'add_on' => 'GadaaCloudCopilot'],
        ];

        $companyRole = Role::where('name', 'company')->first();
        $superadminRole = Role::where('name', 'superadmin')->first();

        foreach ($permissions as $perm) {
            $permissionObj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => $perm['add_on'],
                ]
            );

            if ($companyRole && !$companyRole->hasPermissionTo($permissionObj)) {
                $companyRole->givePermissionTo($permissionObj);
            }
            if ($superadminRole && !$superadminRole->hasPermissionTo($permissionObj)) {
                $superadminRole->givePermissionTo($permissionObj);
            }
        }
    }
}
