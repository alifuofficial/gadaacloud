<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class CustomModulePermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = [
            // Import & Export Operations
            [
                'name'   => 'manage-import-export',
                'module' => 'import-export',
                'label'  => 'Manage Import & Export Operations',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'manage-letters-of-credit',
                'module' => 'import-export',
                'label'  => 'Manage Letters of Credit (LC)',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'create-letters-of-credit',
                'module' => 'import-export',
                'label'  => 'Create Letters of Credit (LC)',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'manage-shipments',
                'module' => 'import-export',
                'label'  => 'Manage Shipping & Customs Logistics',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'create-shipments',
                'module' => 'import-export',
                'label'  => 'Create Shipping Logistics Entry',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'manage-landed-costs',
                'module' => 'import-export',
                'label'  => 'Manage Landed Cost Calculation',
                'add_on' => 'ImportExport',
            ],
            [
                'name'   => 'create-landed-costs',
                'module' => 'import-export',
                'label'  => 'Create Landed Cost Calculation',
                'add_on' => 'ImportExport',
            ],

            // Inventory Management
            [
                'name'   => 'manage-inventory-management',
                'module' => 'inventory-management',
                'label'  => 'Manage Inventory Management',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'manage-stock-adjustments',
                'module' => 'inventory-management',
                'label'  => 'Manage Stock Adjustments & Take',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'create-stock-adjustments',
                'module' => 'inventory-management',
                'label'  => 'Create Stock Adjustments',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'manage-reorder-rules',
                'module' => 'inventory-management',
                'label'  => 'Manage Safety Stock Reorder Thresholds',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'create-reorder-rules',
                'module' => 'inventory-management',
                'label'  => 'Create Reorder Threshold Rules',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'manage-serial-numbers',
                'module' => 'inventory-management',
                'label'  => 'Manage Serial & Chassis Numbers',
                'add_on' => 'InventoryManagement',
            ],
            [
                'name'   => 'create-serial-numbers',
                'module' => 'inventory-management',
                'label'  => 'Register Serial & Chassis Numbers',
                'add_on' => 'InventoryManagement',
            ],

            // Ethiopian Calendar
            [
                'name'   => 'manage-ethiopian-calendar',
                'module' => 'ethiopian-calendar',
                'label'  => 'Manage Ethiopian Calendar',
                'add_on' => 'EthiopianCalendar',
            ],

            // Gadaacloud Studio
            [
                'name'   => 'manage-gadaacloud-studio',
                'module' => 'gadaacloud-studio',
                'label'  => 'Manage Gadaacloud Studio',
                'add_on' => 'GadaacloudStudio',
            ],
            [
                'name'   => 'manage-approvals',
                'module' => 'gadaacloud-studio',
                'label'  => 'Manage Approvals & Workflows',
                'add_on' => 'GadaacloudStudio',
            ],
        ];

        $companyRole = Role::where('name', 'company')->first();
        $superadminRole = Role::where('name', 'superadmin')->first();

        $createdPermissionObjs = [];

        foreach ($permissions as $perm) {
            $permissionObj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label'  => $perm['label'],
                    'add_on' => $perm['add_on'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Ensure add_on, module, and label are updated if pre-existing
            $permissionObj->update([
                'module' => $perm['module'],
                'label'  => $perm['label'],
                'add_on' => $perm['add_on'],
            ]);

            $createdPermissionObjs[] = $permissionObj;

            if ($companyRole && !$companyRole->hasPermissionTo($permissionObj)) {
                $companyRole->givePermissionTo($permissionObj);
            }

            if ($superadminRole && !$superadminRole->hasPermissionTo($permissionObj)) {
                $superadminRole->givePermissionTo($permissionObj);
            }
        }

        // Give permissions to all existing Company Users
        $companyUsers = User::where('type', 'company')->get();
        foreach ($companyUsers as $companyUser) {
            foreach ($createdPermissionObjs as $pObj) {
                if (!$companyUser->hasPermissionTo($pObj)) {
                    $companyUser->givePermissionTo($pObj);
                }
            }
        }
    }
}
