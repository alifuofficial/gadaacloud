<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function Dashboard(Request $request)
    {
        if(Auth::user()->type === 'superadmin') {
            return $this->superAdminDashboard();
        }

        return $this->regularDashboard();
    }

    private function superAdminDashboard()
    {
        $dbDriver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $monthSelect = $dbDriver === 'pgsql' ? 'EXTRACT(MONTH FROM created_at) as month' : 'MONTH(created_at) as month';

        // 1. Monthly revenue and orders velocity (Current Year)
        $orderData = Order::selectRaw($monthSelect . ', COUNT(*) as count, SUM(price) as payments')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chartData = [];
        $isDemo = config('app.is_demo');

        for ($i = 1; $i <= 12; $i++) {
            if ($isDemo) {
                $chartData[] = [
                    'month' => $months[$i-1],
                    'orders' => rand(5, 20),
                    'payments' => rand(500, 5000)
                ];
            } else {
                $chartData[] = [
                    'month' => $months[$i-1],
                    'orders' => intval($orderData[$i]->count ?? 0),
                    'payments' => floatval($orderData[$i]->payments ?? 0)
                ];
            }
        }

        // 2. Tenant lifecycle & MRR metrics
        $companies = User::where('type', 'company')->get();
        $totalCompanies = $companies->count();
        $nowStr = now()->format('Y-m-d');
        $fourteenDaysLater = now()->addDays(14)->format('Y-m-d');

        $activePaidCount = 0;
        $activeTrialCount = 0;
        $freePlanCount = 0;
        $expiredCount = 0;
        $mrrEtb = 0;
        $mrrUsd = 0;
        $planDistribution = [];
        $expiringCompanies = [];

        $allPlans = Plan::all()->keyBy('id');

        foreach ($companies as $comp) {
            $plan = $comp->active_plan ? ($allPlans[$comp->active_plan] ?? null) : null;
            $planName = $plan ? $plan->name : 'No Plan';
            $planDistribution[$planName] = ($planDistribution[$planName] ?? 0) + 1;

            $isExpired = false;
            if (!empty($comp->plan_expire_date) && $comp->plan_expire_date < $nowStr) {
                $isExpired = true;
            }

            if (empty($comp->active_plan) || $comp->active_plan == 0 || $isExpired) {
                $expiredCount++;
            } elseif ($plan && $plan->free_plan) {
                $freePlanCount++;
            } elseif (!empty($comp->trial_expire_date) && $comp->trial_expire_date >= $nowStr && empty($comp->is_trial_done)) {
                $activeTrialCount++;
            } else {
                $activePaidCount++;
            }

            // MRR contribution
            if (!empty($comp->active_plan) && $comp->active_plan > 0 && !$isExpired && $plan && !$plan->free_plan) {
                $latestOrder = Order::where('created_by', $comp->id)
                    ->where('payment_status', 'succeeded')
                    ->latest()
                    ->first();
                if ($latestOrder) {
                    $curr = $latestOrder->currency ?: 'USD';
                    $orderPrice = floatval($latestOrder->price);
                    $monthlyAmount = ($latestOrder->card_exp_year || ($comp->plan_expire_date && \Carbon\Carbon::parse($comp->plan_expire_date)->diffInDays(\Carbon\Carbon::parse($latestOrder->created_at)) > 40))
                        ? ($orderPrice / 12)
                        : $orderPrice;
                    if (strtoupper($curr) === 'ETB') {
                        $mrrEtb += $monthlyAmount;
                    } else {
                        $mrrUsd += $monthlyAmount;
                    }
                } else {
                    $mrrUsd += floatval($plan->package_price_monthly > 0 ? $plan->package_price_monthly : ($plan->package_price_yearly / 12));
                }
            }

            // Expiring soon watchlist (< 14 days)
            if (!empty($comp->plan_expire_date) && $comp->plan_expire_date >= $nowStr && $comp->plan_expire_date <= $fourteenDaysLater) {
                $daysLeft = now()->diffInDays(\Carbon\Carbon::parse($comp->plan_expire_date), false);
                $expiringCompanies[] = [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'email' => $comp->email,
                    'plan_name' => $plan ? $plan->name : 'Custom Plan',
                    'expire_date' => $comp->plan_expire_date,
                    'days_left' => max(0, $daysLeft),
                ];
            }
        }

        // Format Plan Distribution for Donut/Pie Chart
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
        $planChartData = [];
        $colorIdx = 0;
        foreach ($planDistribution as $name => $count) {
            $planChartData[] = [
                'name' => $name,
                'count' => $count,
                'color' => $colors[$colorIdx % count($colors)],
            ];
            $colorIdx++;
        }

        // 3. Top Adopted Modules
        $topModules = \App\Models\UserActiveModule::selectRaw('module, count(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->take(6)
            ->get()
            ->map(function ($row) {
                $addon = \App\Models\AddOn::where('module', $row->module)->first();
                return [
                    'module' => $row->module,
                    'name' => $addon ? $addon->name : $row->module,
                    'count' => intval($row->count),
                ];
            });

        // 4. Recent Companies (Latest 5)
        $recentCompanies = User::where('type', 'company')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($comp) use ($nowStr, $allPlans) {
                $plan = $comp->active_plan ? ($allPlans[$comp->active_plan] ?? null) : null;
                $isExpired = !empty($comp->plan_expire_date) && $comp->plan_expire_date < $nowStr;
                return [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'email' => $comp->email,
                    'plan_name' => $plan ? $plan->name : 'No Plan',
                    'is_free_plan' => $plan ? (bool)$plan->free_plan : false,
                    'is_expired' => $isExpired,
                    'created_at' => $comp->created_at->format('M d, Y'),
                    'total_users' => User::where('created_by', $comp->id)->count() + 1,
                    'status' => $comp->is_disable == 1 ? 'Disabled' : ($isExpired ? 'Expired' : 'Active'),
                ];
            });

        // 5. Currency revenue breakdown
        $currencyBreakdown = Order::selectRaw('currency, SUM(price) as total')
            ->where('payment_status', 'succeeded')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->toArray();

        // 6. Pending bank transfers count
        $pendingTransfers = \Illuminate\Support\Facades\Schema::hasTable('bank_transfer_payments')
            ? \Illuminate\Support\Facades\DB::table('bank_transfer_payments')->whereIn('status', ['pending', 'Pending'])->count()
            : 0;

        // 7. Failed jobs count
        $failedJobs = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
            ? \Illuminate\Support\Facades\DB::table('failed_jobs')->count()
            : 0;

        // 8. Storage usage
        $storageUsedBytes = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('media')) {
                $storageUsedBytes = floatval(\App\Models\Media::sum('size') ?? 0);
            }
        } catch (\Throwable $e) {}

        // 9. AI token usage
        $aiTotalTokens = 0;
        $aiTotalCost = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('copilot_token_usages')) {
                $aiTotalTokens = intval(\Illuminate\Support\Facades\DB::table('copilot_token_usages')->sum('total_tokens') ?? 0);
                $aiTotalCost = floatval(\Illuminate\Support\Facades\DB::table('copilot_token_usages')->sum('token_cost') ?? 0);
            }
        } catch (\Throwable $e) {}

        // 10. Growth this month vs last month
        $newCompaniesThisMonth = User::where('type', 'company')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $lastMonth = now()->subMonth();
        $newCompaniesLastMonth = User::where('type', 'company')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();
        $tenantGrowthRate = $newCompaniesLastMonth > 0
            ? round((($newCompaniesThisMonth - $newCompaniesLastMonth) / $newCompaniesLastMonth) * 100, 1)
            : ($newCompaniesThisMonth > 0 ? 100 : 0);

        return Inertia::render('SuperAdminDashboard', [
            'stats' => [
                'order_payments' => floatval(Order::sum('price') ?? 0),
                'total_orders' => intval(Order::count()),
                'total_plans' => intval(Plan::count()),
                'total_companies' => $totalCompanies,
                'active_paid' => $activePaidCount,
                'active_trial' => $activeTrialCount,
                'free_plan' => $freePlanCount,
                'expired_churn' => $expiredCount,
                'mrr_etb' => round($mrrEtb, 2),
                'mrr_usd' => round($mrrUsd, 2),
                'arr_usd' => round($mrrUsd * 12, 2),
                'currency_breakdown' => $currencyBreakdown,
                'pending_transfers' => $pendingTransfers,
                'new_companies_this_month' => $newCompaniesThisMonth,
                'tenant_growth_rate' => $tenantGrowthRate,
                'ai_total_tokens' => $aiTotalTokens,
                'ai_total_cost' => $aiTotalCost,
            ],
            'chartData' => $chartData,
            'planChartData' => $planChartData,
            'topModules' => $topModules,
            'expiringCompanies' => $expiringCompanies,
            'recentCompanies' => $recentCompanies,
            'telemetry' => [
                'storage_used_bytes' => $storageUsedBytes,
                'failed_jobs' => $failedJobs,
                'php_version' => PHP_VERSION,
                'db_driver' => $dbDriver,
                'active_modules_count' => count((new \App\Classes\Module())->allEnabled()),
            ]
        ]);
    }

    private function regularDashboard()
    {
        $packagesPath = base_path('packages/workdo');

        // find dashboard menu from all  active package and redirect if found
        if (is_dir($packagesPath)) {
            foreach (glob($packagesPath . '/*/src/Resources/js/menus/company-menu.ts') as $menuFile) {
                preg_match('/packages\/workdo\/([^\/]+)\//', $menuFile, $moduleMatch);
                $moduleName = $moduleMatch[1] ?? null;
                    $content = file_get_contents($menuFile);
                    if (preg_match("/parent:\s*['\"]dashboard['\"]/", $content)) {
                        preg_match("/href:\s*route\(['\"]([^'\"]+)['\"]/", $content, $routeMatch);
                        preg_match("/permission:\s*['\"]([^'\"]+)['\"]/", $content, $permMatch);
                        if (!empty($routeMatch[1]) && !empty($permMatch[1]) &&  Module_is_active($moduleName) && Auth::user()->can($permMatch[1])) {
                            return redirect()->route($routeMatch[1]);
                        }
                }
            }
        }

        return Inertia::render('dashboard');
    }
}
