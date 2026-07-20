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

        return Inertia::render('SuperAdminDashboard', [
            'stats' => [
                'order_payments' => floatval(Order::sum('price') ?? 0),
                'total_orders' => intval(Order::count()),
                'total_plans' => intval(Plan::count()),
                'total_companies' => intval(User::where('type', 'company')->count()),
            ],
            'chartData' => $chartData
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
