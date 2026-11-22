<?php

namespace Workdo\EIMS\Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class EimsPermissionTableSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();
        Artisan::call('cache:clear');

        $permissions = [
            ['name' => 'manage-eims', 'module' => 'eims', 'label' => 'Manage EIMS Dashboard'],
            ['name' => 'view-eims-invoices', 'module' => 'eims', 'label' => 'View EIMS Cleared Invoices'],
            ['name' => 'register-eims-invoices', 'module' => 'eims', 'label' => 'Register EIMS Invoices with MoR'],
            ['name' => 'cancel-eims-invoices', 'module' => 'eims', 'label' => 'Cancel EIMS Invoices'],
            ['name' => 'manage-eims-offline-queue', 'module' => 'eims', 'label' => 'Manage EIMS Offline Queue'],
            ['name' => 'manage-eims-settings', 'module' => 'eims', 'label' => 'Manage EIMS MoR Settings'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permissions as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'EIMS',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($company_role && !$company_role->hasPermissionTo($permission_obj)) {
                $company_role->givePermissionTo($permission_obj);
            }
        }
    }
}
