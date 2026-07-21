<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cookie;
use App\Classes\Module;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if (!$this->isInstalled()) {
            return [];
        }
        $locale = $request->user()->lang ?? $this->getSuperAdminLang();

        if (config('app.is_demo') && Cookie::get('language')) {
            $locale = Cookie::get('language');
        }

        app()->setLocale($locale);

        $languageFile = resource_path('lang/language.json');
        $defaultLanguages = [];
        if (file_exists($languageFile)) {
            $languages = json_decode(file_get_contents($languageFile), true) ?? [];
            $defaultLanguages = array_values($languages);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->toArray(),
                        [
                            'permissions' => $this->getUserPermissions($request->user()),
                            'roles' => $this->getUserRoles($request->user()),
                            'activatedPackages' => ActivatedModule(),
                        ]
                    )
                    : ['activatedPackages' => ActivatedModule()],
                'impersonating' => $request->session()->has('impersonator_id'),
                'lang' => $locale,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'packages' => (new Module())->allModules(),
            'adminAllSetting' =>   $request->user() ?  getAdminAllSetting() : getAdminAllSetting(true),
            'companyAllSetting' => $request->user() ? getCompanyAllSetting($request->user()->id) : [],
            'imageUrlPrefix' =>  getImageUrlPrefix(),
            'baseUrl' => url('/'),
            'currencies' => config('default_currency.currencies', []),
            'defaultLanguages' => $defaultLanguages,
            'is_demo' => config('app.is_demo', false),
        ];
    }

    public function onException($request, $exception)
    {
        if ($exception instanceof AuthorizationException) {
            return redirect()->route('users.index')->with('error', 'Permission denied');
        }

        return parent::onException($request, $exception);
    }

    /**
     * Get user permissions filtered by active plan and packages
     */
    private function getUserPermissions($user): array
    {
        if (method_exists($user, 'getAllPermissions')) {
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();

            // Check if superadmin
            if ($user->hasRole('superadmin')) {
                return $permissions;
            }

            // Find company creator user
            $creator = $user->type === 'company' ? $user : \App\Models\User::find($user->created_by);
            if (!$creator) {
                return $permissions;
            }

            // Check if plan is inactive or expired
            $planExpired = $creator->plan_expire_date && now()->gt($creator->plan_expire_date);
            if ($creator->active_plan == 0 || $planExpired) {
                // Return ONLY essential billing and dashboard permissions
                $allowed = [
                    'manage-dashboard',
                    'manage-plans',
                    'manage-orders',
                    'manage-bank-transfer-requests',
                    'manage-settings'
                ];
                return array_values(array_intersect($permissions, $allowed));
            }

            // If subscribed, filter permissions by active plan modules
            $activeModules = \App\Models\Plan::getUserSubscriptionModules($creator->id);
            $activeModulesLower = array_map('strtolower', $activeModules);

            // Query all permissions from the database to map permission names to their modules
            $permissionModules = \DB::table('permissions')
                ->whereIn('name', $permissions)
                ->pluck('module', 'name')
                ->toArray();

            // Map permission module names to package names (for sub-features that live inside a
            // different addon than their module slug suggests).
            $moduleMap = [
                'sales-proposals' => 'Lead',
                'sales-invoices' => 'Account',
                'purchase-invoices' => 'Account',
                'warehouses' => 'Account',
                'transfers' => 'Account',
                'helpdesk-tickets' => 'SupportTicket',
                'support-ticket' => 'SupportTicket',
                'messenger' => 'Messenger',
                'import-export' => 'ImportExport',
                'inventory-management' => 'InventoryManagement',
            ];

            // All addon module names (StudlyCase), so we can recognise "sub-module groups"
            // like 'employees', 'payrolls', 'attendances', 'leave-applications' ...
            // These permission submodule strings are NOT themselves addons — they live inside
            // a parent package (e.g. 'Hrm'). The package subscription gate is already enforced
            // elsewhere (ActivatedModule() → activatedPackages in JS sidebar + PlanModuleCheck
            // route middleware), so here we must NOT strip them, otherwise subscribed users only
            // see one item per package (e.g. HRM showed only "System Setup").
            $allAddonModulesLower = \App\Models\AddOn::pluck('module')
                ->map(fn($m) => strtolower($m))
                ->unique()
                ->values()
                ->toArray();

            // Core modules always allowed regardless of subscription (case-insensitive)
            $coreModulesLower = ['dashboard', 'users', 'roles', 'settings', 'plans', 'media', 'messenger'];

            return array_values(array_filter($permissions, function($permission) use ($permissionModules, $activeModulesLower, $moduleMap, $allAddonModulesLower, $coreModulesLower) {
                $module = $permissionModules[$permission] ?? null;

                // Always allow permission rows with no recorded module, or core modules
                if (!$module || in_array(strtolower($module), $coreModulesLower)) {
                    return true;
                }

                // Check StudlyCase mapping or exact module_MAP match against subscribed packages
                $packageName = $moduleMap[$module] ?? \Illuminate\Support\Str::studly($module);
                if (in_array(strtolower($packageName), $activeModulesLower)) {
                    return true;
                }

                // The permission's submodule slug (e.g. 'employees', 'payrolls') is not itself
                // an AddOn — meaning it belongs inside a parent package (whichever registered it).
                // Don't strip: the parent-package subscription gate is enforced upstream
                // (activatedPackages in resources/js/utils/menu.ts + PlanModuleCheck middleware).
                if (!in_array(strtolower($packageName), $allAddonModulesLower) && !in_array(strtolower($module), $allAddonModulesLower)) {
                    return true;
                }

                // This sub-module really is an addon the user hasn't subscribed to — filter out.
                return false;
            }));
        }
        return [];
    }

    private function getUserRoles($user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }
        return [];
    }

    /**
     * Get superadmin language if user lang is not set
     */
    private function getSuperAdminLang(): string
    {
        return admin_setting('defaultLanguage') ? admin_setting('defaultLanguage') : 'en';
    }

    private function isInstalled(): bool
    {
        if (File::exists(storage_path('installed')) || env('APP_INSTALLED') === true || env('APP_INSTALLED') === 'true') {
            return true;
        }

        try {
            return \Illuminate\Support\Facades\Schema::hasTable('users');
        } catch (\Exception $e) {
            return false;
        }
    }
}