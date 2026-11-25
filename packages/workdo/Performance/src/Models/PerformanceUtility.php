<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PerformanceUtility extends Model
{
    /**
     * Grant least-privilege Performance permissions to standard employee/staff roles.
     * Company-wide configuration (categories, goal types, templates, bulk deletes) stays restricted to HR/company admins.
     */
    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $staff_permission = [
            'manage-performance',
            'manage-performance-dashboard',

            'manage-performance-indicators',
            'manage-own-performance-indicators',
            'view-performance-indicators',

            'manage-employee-goals',
            'manage-own-employee-goals',
            'view-employee-goals',
            'create-employee-goals',
            'edit-employee-goals',

            'manage-review-cycles',
            'manage-own-review-cycles',
            'view-review-cycles',

            'manage-employee-reviews',
            'manage-own-employee-reviews',
            'view-employee-reviews',
            'conduct-employee-reviews',

            'manage-employee-competencies',
            'manage-own-employee-competencies',

            'manage-employee-feedback',
            'manage-own-employee-feedback',
            'create-employee-feedback',
        ];

        if ($rolename == 'staff') {
            $roles_v = Role::where('name', 'staff')->where('id', $role_id)->first();
            if ($roles_v) {
                foreach ($staff_permission as $permission_v) {
                    $permission = Permission::where('name', $permission_v)->first();
                    if (!empty($permission) && !$roles_v->hasPermissionTo($permission_v)) {
                        $roles_v->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}
