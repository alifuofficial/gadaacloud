<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectSettingsController extends Controller
{
    /**
     * Display the Project Setup & Configuration Settings page.
     */
    public function index()
    {
        if (Auth::user()->can('manage-project') || Auth::user()->can('manage-task-stages')) {
            $settings = [
                'taskly_default_project_scope' => company_setting('taskly_default_project_scope') ?: 'client',
                'taskly_default_internal_category' => company_setting('taskly_default_internal_category') ?: 'opex',
                'taskly_default_currency' => company_setting('taskly_default_currency') ?: 'ETB',
                'taskly_default_hourly_rate' => company_setting('taskly_default_hourly_rate') ?: '50',
                'taskly_enable_evm' => company_setting('taskly_enable_evm') ?? 'on',
                'taskly_enable_construction' => company_setting('taskly_enable_construction') ?? 'on',
                'taskly_enable_logistics' => company_setting('taskly_enable_logistics') ?? 'on',
                'taskly_enable_gantt_dependencies' => company_setting('taskly_enable_gantt_dependencies') ?? 'on',
                'taskly_enable_milestone_approvals' => company_setting('taskly_enable_milestone_approvals') ?? 'on',
                'taskly_demurrage_free_days' => company_setting('taskly_demurrage_free_days') ?: '7',
                'taskly_demurrage_daily_rate' => company_setting('taskly_demurrage_daily_rate') ?: '150',
                'taskly_demurrage_alert_days' => company_setting('taskly_demurrage_alert_days') ?: '3',
                'taskly_timesheet_approval_required' => company_setting('taskly_timesheet_approval_required') ?? 'off',
                'taskly_allow_billable_hours' => company_setting('taskly_allow_billable_hours') ?? 'on',
                'taskly_max_daily_hours' => company_setting('taskly_max_daily_hours') ?: '12',
                'taskly_auto_generate_magic_link' => company_setting('taskly_auto_generate_magic_link') ?? 'on',
                'taskly_client_milestone_approval' => company_setting('taskly_client_milestone_approval') ?? 'on',
                'taskly_client_hide_financials' => company_setting('taskly_client_hide_financials') ?? 'on',
                'taskly_client_show_subtasks' => company_setting('taskly_client_show_subtasks') ?? 'on',
                'taskly_cpi_warning_threshold' => company_setting('taskly_cpi_warning_threshold') ?: '0.90',
                'taskly_spi_warning_threshold' => company_setting('taskly_spi_warning_threshold') ?: '0.90',
            ];

            return Inertia::render('Taskly/SystemSetup/Settings/Index', [
                'settings' => $settings,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    /**
     * Store updated project configurations and preferences.
     */
    public function store(Request $request)
    {
        if (Auth::user()->can('manage-project') || Auth::user()->can('manage-task-stages')) {
            $request->validate([
                'settings' => 'required|array',
                'settings.taskly_default_project_scope' => 'nullable|string|in:client,internal',
                'settings.taskly_default_internal_category' => 'nullable|string|in:capex,opex,rd,strategic',
                'settings.taskly_default_currency' => 'nullable|string|max:10',
                'settings.taskly_default_hourly_rate' => 'nullable|numeric|min:0',
                'settings.taskly_enable_evm' => 'nullable|string|in:on,off',
                'settings.taskly_enable_construction' => 'nullable|string|in:on,off',
                'settings.taskly_enable_logistics' => 'nullable|string|in:on,off',
                'settings.taskly_enable_gantt_dependencies' => 'nullable|string|in:on,off',
                'settings.taskly_enable_milestone_approvals' => 'nullable|string|in:on,off',
                'settings.taskly_demurrage_free_days' => 'nullable|integer|min:0',
                'settings.taskly_demurrage_daily_rate' => 'nullable|numeric|min:0',
                'settings.taskly_demurrage_alert_days' => 'nullable|integer|min:0',
                'settings.taskly_timesheet_approval_required' => 'nullable|string|in:on,off',
                'settings.taskly_allow_billable_hours' => 'nullable|string|in:on,off',
                'settings.taskly_max_daily_hours' => 'nullable|numeric|min:1|max:24',
                'settings.taskly_auto_generate_magic_link' => 'nullable|string|in:on,off',
                'settings.taskly_client_milestone_approval' => 'nullable|string|in:on,off',
                'settings.taskly_client_hide_financials' => 'nullable|string|in:on,off',
                'settings.taskly_client_show_subtasks' => 'nullable|string|in:on,off',
                'settings.taskly_cpi_warning_threshold' => 'nullable|numeric|min:0|max:2',
                'settings.taskly_spi_warning_threshold' => 'nullable|numeric|min:0|max:2',
            ]);

            $createdBy = creatorId();
            $incomingSettings = $request->input('settings', []);

            DB::transaction(function () use ($incomingSettings, $createdBy) {
                foreach ($incomingSettings as $key => $value) {
                    // Normalize booleans to 'on' or 'off'
                    if (is_bool($value)) {
                        $val = $value ? 'on' : 'off';
                    } elseif ($value === null) {
                        $val = '';
                    } else {
                        $val = (string) $value;
                    }

                    setSetting($key, $val, $createdBy);
                }
            });

            return redirect()->back()->with('success', __('Project configurations and preferences saved successfully.'));
        }

        return redirect()->back()->with('error', __('Permission denied'));
    }
}
