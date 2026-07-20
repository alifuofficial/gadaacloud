<?php

namespace Workdo\Hrm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Hrm\Models\Employee;

class OrgChartController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-employees')) {
            abort(403, __('Permission denied'));
        }

        $employees = Employee::with(['user', 'branch', 'department', 'designation'])
            ->where('created_by', creatorId())
            ->get();

        // Recursive tree builder
        $buildTree = function ($employees, $managerId = null) use (&$buildTree) {
            $branch = [];
            $nodes = $employees->where('manager_id', $managerId);

            foreach ($nodes as $node) {
                $branch[] = [
                    'id' => 'emp_' . $node->id,
                    'name' => $node->user->name ?? 'Unknown',
                    'email' => $node->user->email ?? '',
                    'designation' => $node->designation->designation_name ?? '—',
                    'department' => $node->department->department_name ?? '—',
                    'branch' => $node->branch->branch_name ?? '—',
                    'avatar' => $node->user->avatar ?? null,
                    'children' => $buildTree($employees, $node->id)
                ];
            }
            return $branch;
        };

        // Root nodes are employees without a manager, or whose manager is not in the list (orphan loops safety)
        $employeeIds = $employees->pluck('id')->toArray();
        $rootEmployees = $employees->filter(function ($emp) use ($employeeIds) {
            return is_null($emp->manager_id) || !in_array($emp->manager_id, $employeeIds);
        });

        $chartData = [];
        foreach ($rootEmployees as $root) {
            $chartData[] = [
                'id' => 'emp_' . $root->id,
                'name' => $root->user->name ?? 'Unknown',
                'email' => $root->user->email ?? '',
                'designation' => $root->designation->designation_name ?? '—',
                'department' => $root->department->department_name ?? '—',
                'branch' => $root->branch->branch_name ?? '—',
                'avatar' => $root->user->avatar ?? null,
                'children' => $buildTree($employees, $root->id)
            ];
        }

        return Inertia::render('Hrm/OrgChart/Index', [
            'chartData' => $chartData
        ]);
    }
}
