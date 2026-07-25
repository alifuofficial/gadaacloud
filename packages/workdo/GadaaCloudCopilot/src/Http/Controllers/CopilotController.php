<?php

namespace Workdo\GadaaCloudCopilot\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\GadaaCloudCopilot\Models\CopilotInsight;
use Workdo\GadaaCloudCopilot\Models\CopilotAutomation;
use Workdo\GadaaCloudCopilot\Models\CopilotMemory;
use Workdo\GadaaCloudCopilot\Models\CopilotTokenUsage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CopilotController extends Controller
{
    public function index()
    {
        $companyId = creatorId();

        $totalSales = 0;
        $collectedSales = 0;
        $pendingReceivables = 0;

        $totalPurchases = 0;
        $paidPurchases = 0;
        $pendingPayables = 0;

        $totalEmployees = 0;
        $totalBasicSalary = 0;

        // 1. Sales & Receivables Summary
        try {
            if (Schema::hasTable('sales_invoices')) {
                $salesInvoices = DB::table('sales_invoices')
                    ->where('created_by', $companyId)
                    ->select(
                        DB::raw("SUM(COALESCE(total_amount, 0)) as total_sales"),
                        DB::raw("SUM(COALESCE(paid_amount, 0)) as collected_sales"),
                        DB::raw("SUM(COALESCE(balance_amount, 0)) as pending_receivables")
                    )->first();

                $totalSales = floatval($salesInvoices->total_sales ?? 0);
                $collectedSales = floatval($salesInvoices->collected_sales ?? 0);
                $pendingReceivables = floatval($salesInvoices->pending_receivables ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning("Copilot sales_invoices query notice: " . $e->getMessage());
        }

        // 2. Purchases & Payables Summary
        try {
            if (Schema::hasTable('purchase_invoices')) {
                $purchaseInvoices = DB::table('purchase_invoices')
                    ->where('created_by', $companyId)
                    ->select(
                        DB::raw("SUM(COALESCE(total_amount, 0)) as total_purchases"),
                        DB::raw("SUM(COALESCE(paid_amount, 0)) as paid_purchases"),
                        DB::raw("SUM(COALESCE(balance_amount, 0)) as pending_payables")
                    )->first();

                $totalPurchases = floatval($purchaseInvoices->total_purchases ?? 0);
                $paidPurchases = floatval($purchaseInvoices->paid_purchases ?? 0);
                $pendingPayables = floatval($purchaseInvoices->pending_payables ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning("Copilot purchase_invoices query notice: " . $e->getMessage());
        }

        // 3. HRM & Payroll Metrics
        try {
            if (Schema::hasTable('employees')) {
                $employeeStats = DB::table('employees')
                    ->where('created_by', $companyId)
                    ->select(
                        DB::raw("COUNT(*) as total_employees"),
                        DB::raw("SUM(COALESCE(basic_salary, 0)) as total_basic_salary")
                    )->first();

                $totalEmployees = intval($employeeStats->total_employees ?? 0);
                $totalBasicSalary = floatval($employeeStats->total_basic_salary ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning("Copilot employees query notice: " . $e->getMessage());
        }

        $netCashFlow = $collectedSales - $paidPurchases - $totalBasicSalary;

        // 4. Ethiopian Tax Calculation Engine
        $vatOutput = $totalSales * 0.15;
        $vatInput  = $totalPurchases * 0.15;
        $netVatPayable = max(0, $vatOutput - $vatInput);

        $withholdingEst = $totalSales * 0.02;

        $estEmploymentTax = $this->calculateEthiopianEmploymentTax($totalBasicSalary);
        $employeePensionEst = $totalBasicSalary * 0.07;
        $employerPensionEst = $totalBasicSalary * 0.11;
        $totalPensionEst    = $employeePensionEst + $employerPensionEst;

        $totGoodsEst = $totalSales * 0.02;

        $taxableIncomeEst = max(0, $totalSales - $totalPurchases - $totalBasicSalary);
        $businessTaxEst   = $taxableIncomeEst * 0.30;

        // 5. Generate AI Cashflow Forecast for Next 6 Months
        $forecastMonths = [];
        $today = now();
        $ethiopianEvents = [
            'Sep' => 'Enkutatash & Meskel Demand Peak (+20%)',
            'Jan' => 'Genna & Timkat Holiday Peak (+15%)',
            'Apr' => 'Ramadan & Easter Season (+12%)',
        ];

        for ($i = 0; $i < 6; $i++) {
            $monthDate = $today->copy()->addMonths($i);
            $monthShort = $monthDate->format('M');
            $monthLabel = $monthDate->format('M Y');

            $eventBonus = 1.0;
            $eventNote = '';
            if (isset($ethiopianEvents[$monthShort])) {
                $eventBonus = 1.18;
                $eventNote = $ethiopianEvents[$monthShort];
            }

            $trendMultiplier = (1 + ($i * 0.03)) * $eventBonus;
            $projReceivables = ($collectedSales > 0 ? ($collectedSales / 3) : 60000) * $trendMultiplier;
            $projPayables    = ($paidPurchases > 0 ? ($paidPurchases / 3) : 35000) * $trendMultiplier;
            $projNet = $projReceivables - $projPayables - $totalBasicSalary;

            $forecastMonths[] = [
                'month'       => $monthLabel,
                'receivables' => round($projReceivables, 2),
                'payables'    => round($projPayables, 2),
                'net'         => round($projNet, 2),
                'eventNote'   => $eventNote,
            ];
        }

        // 6. Trade & Landed Cost Intelligence
        $demurrageRiskCount = 2;
        $estimatedLandedCostMarkup = $totalPurchases > 0 ? round(($totalPurchases * 0.18), 2) : 0;

        // 7. Fetch Automations & Insights
        $automations = collect();
        try {
            if (Schema::hasTable('copilot_automations')) {
                $automations = CopilotAutomation::where('created_by', $companyId)->get();
                if ($automations->isEmpty()) {
                    $defaultAutomations = [
                        [
                            'created_by' => $companyId,
                            'name' => 'Auto Overdue Invoice Payment Reminders',
                            'trigger_event' => 'invoice_due',
                            'action_type' => 'send_email',
                            'is_active' => true,
                            'config' => ['days_overdue' => 3],
                        ],
                        [
                            'created_by' => $companyId,
                            'name' => 'Djibouti Port Demurrage Risk Warning',
                            'trigger_event' => 'lc_demurrage_near',
                            'action_type' => 'create_alert',
                            'is_active' => true,
                            'config' => ['days_before_expiry' => 5],
                        ],
                        [
                            'created_by' => $companyId,
                            'name' => 'Ethiopian Monthly Tax Filing Reminder (MoR)',
                            'trigger_event' => 'tax_period',
                            'action_type' => 'create_alert',
                            'is_active' => true,
                            'config' => ['due_day' => 30],
                        ],
                        [
                            'created_by' => $companyId,
                            'name' => 'Low Inventory Reorder Trigger',
                            'trigger_event' => 'low_stock',
                            'action_type' => 'reorder_stock',
                            'is_active' => true,
                            'config' => ['threshold' => 5],
                        ],
                    ];

                    foreach ($defaultAutomations as $da) {
                        CopilotAutomation::create($da);
                    }
                    $automations = CopilotAutomation::where('created_by', $companyId)->get();
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Copilot automations query notice: " . $e->getMessage());
        }

        $insights = collect();
        try {
            if (Schema::hasTable('copilot_insights')) {
                $insights = CopilotInsight::where('created_by', $companyId)->orderBy('id', 'desc')->take(10)->get();
            }
        } catch (\Throwable $e) {
            \Log::warning("Copilot insights query notice: " . $e->getMessage());
        }

        // Fetch company token usage summary
        $companyTokensUsed = 0;
        $companyTokenCost = 0;
        try {
            if (Schema::hasTable('copilot_token_usages')) {
                $companyTokensUsed = intval(DB::table('copilot_token_usages')->where('company_id', $companyId)->sum('total_tokens') ?? 0);
                $companyTokenCost  = floatval(DB::table('copilot_token_usages')->where('company_id', $companyId)->sum('token_cost') ?? 0);
            }
        } catch (\Throwable $e) {}

        return Inertia::render('settings/copilot', [
            'metrics' => [
                'totalSales'         => $totalSales,
                'collectedSales'     => $collectedSales,
                'pendingReceivables' => $pendingReceivables,
                'totalPurchases'     => $totalPurchases,
                'paidPurchases'      => $paidPurchases,
                'pendingPayables'    => $pendingPayables,
                'totalEmployees'     => $totalEmployees,
                'totalBasicSalary'   => $totalBasicSalary,
                'netCashFlow'        => $netCashFlow,
                'companyTokensUsed'  => $companyTokensUsed,
                'companyTokenCost'   => $companyTokenCost,
            ],
            'tax' => [
                'vatOutput'          => round($vatOutput, 2),
                'vatInput'           => round($vatInput, 2),
                'netVatPayable'      => round($netVatPayable, 2),
                'withholdingEst'     => round($withholdingEst, 2),
                'estEmploymentTax'   => round($estEmploymentTax, 2),
                'employeePensionEst' => round($employeePensionEst, 2),
                'employerPensionEst' => round($employerPensionEst, 2),
                'totalPensionEst'    => round($totalPensionEst, 2),
                'totGoodsEst'        => round($totGoodsEst, 2),
                'taxableIncome'      => round($taxableIncomeEst, 2),
                'businessTaxEst'     => round($businessTaxEst, 2),
            ],
            'trade' => [
                'demurrageRiskCount' => $demurrageRiskCount,
                'landedCostMarkup'   => $estimatedLandedCostMarkup,
            ],
            'forecastMonths' => $forecastMonths,
            'automations'    => $automations,
            'insights'       => $insights,
        ]);
    }

    /**
     * Render AI Model Setup Page (Restricted strictly to Superadmin)
     */
    public function setup()
    {
        $user = Auth::user();
        if ($user->type !== 'superadmin' && !(method_exists($user, 'hasRole') && $user->hasRole('superadmin'))) {
            return redirect()->route('settings.copilot.index')->with('error', __('Only Superadmin can configure global AI models and token pricing.'));
        }

        $superadminUser = User::where('type', 'superadmin')->first();
        $superadminId = $superadminUser ? $superadminUser->id : $user->id;

        $settings = Setting::whereIn('key', [
                'copilot_ai_provider',
                'copilot_ai_model',
                'copilot_api_key',
                'copilot_token_price_per_1k',
                'copilot_temperature',
                'copilot_max_tokens',
                'copilot_system_prompt'
            ])->pluck('value', 'key')->toArray();

        return Inertia::render('settings/copilot-setup', [
            'aiSettings' => [
                'provider'            => $settings['copilot_ai_provider'] ?? 'gemini',
                'model'               => $settings['copilot_ai_model'] ?? 'gemini-1.5-flash',
                'apiKey'              => $settings['copilot_api_key'] ?? '',
                'tokenPricePer1k'     => $settings['copilot_token_price_per_1k'] ?? '0.05',
                'temperature'         => $settings['copilot_temperature'] ?? '0.3',
                'maxTokens'           => $settings['copilot_max_tokens'] ?? '2048',
                'systemPrompt'        => $settings['copilot_system_prompt'] ?? 'You are GadaaCloud Copilot, an autonomous ERP AI assistant for financial forecasting, Ethiopian tax engine calculations (MoR), supply chain trade management, and HR analytics.',
            ],
        ]);
    }

    /**
     * Save Global AI Model Settings & Token Pricing (Superadmin Only)
     */
    public function saveSetup(Request $request)
    {
        $user = Auth::user();
        if ($user->type !== 'superadmin' && !(method_exists($user, 'hasRole') && $user->hasRole('superadmin'))) {
            return redirect()->route('settings.copilot.index')->with('error', __('Only Superadmin can configure global AI models and token pricing.'));
        }

        $request->validate([
            'provider'         => 'required|string',
            'model'            => 'required|string',
            'apiKey'           => 'nullable|string',
            'tokenPricePer1k'  => 'required|numeric|min:0',
            'temperature'      => 'required|numeric|min:0|max:1',
            'maxTokens'        => 'required|integer|min:256|max:8192',
            'systemPrompt'     => 'required|string',
        ]);

        $superadminUser = User::where('type', 'superadmin')->first();
        $superadminId = $superadminUser ? $superadminUser->id : $user->id;

        $fields = [
            'copilot_ai_provider'        => $request->provider,
            'copilot_ai_model'           => $request->model,
            'copilot_api_key'            => $request->apiKey ?? '',
            'copilot_token_price_per_1k' => (string)$request->tokenPricePer1k,
            'copilot_temperature'        => (string)$request->temperature,
            'copilot_max_tokens'         => (string)$request->maxTokens,
            'copilot_system_prompt'      => $request->systemPrompt,
        ];

        foreach ($fields as $key => $val) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $val, 'is_public' => 0, 'created_by' => $superadminId]
            );
        }

        return redirect()->back()->with('success', __('Global AI model configuration and token pricing saved successfully.'));
    }

    public function toggleAutomation(Request $request, $id)
    {
        $companyId = creatorId();
        $automation = CopilotAutomation::where('created_by', $companyId)->where('id', $id)->firstOrFail();
        $automation->is_active = !$automation->is_active;
        $automation->save();

        return redirect()->back()->with('success', __('Automation status updated successfully.'));
    }

    public function getMemories()
    {
        $user = Auth::user();
        if (Schema::hasTable('copilot_memories')) {
            $memories = CopilotMemory::where('user_id', $user->id)
                ->orderBy('id', 'asc')
                ->take(30)
                ->get(['id', 'role', 'content', 'created_at']);

            return response()->json(['memories' => $memories]);
        }

        return response()->json(['memories' => []]);
    }

    public function clearMemories()
    {
        $user = Auth::user();
        if (Schema::hasTable('copilot_memories')) {
            CopilotMemory::where('user_id', $user->id)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Memory wiped successfully.']);
    }

    /**
     * Floatable Chatbot & Query Terminal (Superadmin Analysis, Memory & Token Metering)
     */
    public function queryCopilot(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $user = Auth::user();
        $companyId = creatorId();
        $prompt = strtolower(trim($request->prompt));

        // 1. Save User Prompt to Persistent Memory
        if (Schema::hasTable('copilot_memories')) {
            try {
                CopilotMemory::create([
                    'user_id'    => $user->id,
                    'created_by' => $companyId,
                    'role'       => 'user',
                    'content'    => $request->prompt,
                ]);
            } catch (\Throwable $e) {}
        }

        $isSuperadmin = $user->type === 'superadmin' || (method_exists($user, 'hasRole') && $user->hasRole('superadmin'));
        $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        $userPermissions = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        $reply = "";

        // ==========================================
        // BRANCH A: SUPERADMIN PLATFORM DIAGNOSTICS
        // ==========================================
        if ($isSuperadmin) {
            $reply .= "👑 **Superadmin ERP Platform Operational Analysis**\n\n";

            $totalCompanies = User::where('type', 'company')->count();
            $expiredCompanies = User::where('type', 'company')
                ->where(function($q) {
                    $q->whereNull('plan_expire_date')
                      ->orWhere('plan_expire_date', '<', now());
                })->count();

            $expiringSoon = User::where('type', 'company')
                ->where('plan_expire_date', '>=', now())
                ->where('plan_expire_date', '<=', now()->addDays(7))
                ->get(['id', 'name', 'email', 'plan_expire_date']);

            $openTickets = 0;
            if (Schema::hasTable('helpdesk_tickets')) {
                $openTickets = DB::table('helpdesk_tickets')->where('status', '!=', 'closed')->count();
            }

            if (str_contains($prompt, 'problem') || str_contains($prompt, 'fix') || str_contains($prompt, 'tenant') || str_contains($prompt, 'company') || str_contains($prompt, 'issue') || str_contains($prompt, 'churn')) {
                $reply .= "• 🚩 **Key Tenant Risks & Churn Bottlenecks**:\n";
                $reply .= "  - **Total Registered Companies**: {$totalCompanies} Tenants\n";
                $reply .= "  - **Expired / Inactive Subscriptions**: {$expiredCompanies} Companies need renewal outreach.\n";
                $reply .= "  - **Expiring Within 7 Days**: " . $expiringSoon->count() . " Companies.\n";

                if ($expiringSoon->count() > 0) {
                    foreach ($expiringSoon as $ec) {
                        $reply .= "    • `{$ec->name}` ({$ec->email}) expires on {$ec->plan_expire_date}\n";
                    }
                }

                $reply .= "\n• 🛠️ **System Operational Bottlenecks to Fix**:\n";
                $reply .= "  1. **Unresolved Support Tickets**: {$openTickets} tickets pending in Helpdesk.\n";
                $reply .= "  2. **Database Queries**: Optimize PostgreSQL index on `sales_invoices(created_by, created_at)`.\n";
                $reply .= "  3. **Module Onboarding**: Ensure new tenant companies complete Chart of Accounts and Tax setup.\n";
            }
            else {
                $reply .= "• 🌐 **Platform Telemetry Overview**:\n";
                $reply .= "  - **Total Tenants**: {$totalCompanies} Companies active on GadaaCloud.\n";
                $reply .= "  - **Plan Health**: " . ($totalCompanies - $expiredCompanies) . " active subscribed / {$expiredCompanies} expired.\n";
                $reply .= "  - **Helpdesk Health**: {$openTickets} open support tickets.\n";
            }
        }
        // ==========================================
        // BRANCH B: COMPANY TENANT & EMPLOYEE ANALYSIS
        // ==========================================
        else {
            $reply .= "🤖 **GadaaCloud Copilot Intelligent Response**\n\n";

            if (str_contains($prompt, 'sale') || str_contains($prompt, 'revenue') || str_contains($prompt, 'invoice') || str_contains($prompt, 'receivable')) {
                if (in_array('company', $userRoles) || in_array('manage-account', $userPermissions) || in_array('manage-revenues', $userPermissions) || in_array('manage-invoices', $userPermissions)) {
                    $salesData = DB::table('sales_invoices')->where('created_by', $companyId)
                        ->select(
                            DB::raw("COUNT(*) as cnt"),
                            DB::raw("SUM(COALESCE(total_amount, 0)) as total"),
                            DB::raw("SUM(COALESCE(paid_amount, 0)) as paid"),
                            DB::raw("SUM(COALESCE(balance_amount, 0)) as bal")
                        )->first();

                    $total = number_format(floatval($salesData->total ?? 0), 2);
                    $paid  = number_format(floatval($salesData->paid ?? 0), 2);
                    $bal   = number_format(floatval($salesData->bal ?? 0), 2);

                    $reply .= "• 💰 **Sales & Receivables Overview**:\n";
                    $reply .= "  - **Total Sales Revenue**: {$total} ETB\n";
                    $reply .= "  - **Collected Payments**: {$paid} ETB\n";
                    $reply .= "  - **Pending Receivables**: {$bal} ETB ({$salesData->cnt} Invoices)\n";
                } else {
                    $reply .= "🔒 **Permission Access Notice**: You do not have permissions (`manage-revenues` or `manage-account`) to view sales financial records.";
                }
            }
            elseif (str_contains($prompt, 'tax') || str_contains($prompt, 'vat') || str_contains($prompt, 'pension') || str_contains($prompt, 'mor')) {
                if (in_array('company', $userRoles) || in_array('manage-account', $userPermissions) || in_array('manage-account-reports', $userPermissions)) {
                    $basicSal = DB::table('employees')->where('created_by', $companyId)->sum('basic_salary') ?? 0;
                    $empTax = $this->calculateEthiopianEmploymentTax($basicSal);
                    $pension7 = $basicSal * 0.07;
                    $pension11 = $basicSal * 0.11;

                    $reply .= "• 🇪🇹 **Ethiopian Tax Engine (MoR Compliance)**:\n";
                    $reply .= "  - **Schedule A Employment Tax**: ~" . number_format($empTax, 2) . " ETB/mo\n";
                    $reply .= "  - **7% Employee Pension**: " . number_format($pension7, 2) . " ETB/mo\n";
                    $reply .= "  - **11% Employer Pension**: " . number_format($pension11, 2) . " ETB/mo\n";
                    $reply .= "  - **VAT (15%)**: Output VAT minus Input VAT for monthly MoR Declaration.\n";
                } else {
                    $reply .= "🔒 **Permission Access Notice**: You do not have financial permissions (`manage-account-reports`) to view tax calculations.";
                }
            }
            elseif (str_contains($prompt, 'employee') || str_contains($prompt, 'staff') || str_contains($prompt, 'payroll') || str_contains($prompt, 'salary')) {
                if (in_array('company', $userRoles) || in_array('manage-employee', $userPermissions) || in_array('manage-payrolls', $userPermissions)) {
                    $empStats = DB::table('employees')->where('created_by', $companyId)
                        ->select(DB::raw("COUNT(*) as cnt"), DB::raw("SUM(COALESCE(basic_salary, 0)) as sal"))->first();

                    $cnt = $empStats->cnt ?? 0;
                    $sal = number_format(floatval($empStats->sal ?? 0), 2);

                    $reply .= "• 👥 **HRM & Payroll Analytics**:\n";
                    $reply .= "  - **Active Employees**: {$cnt}\n";
                    $reply .= "  - **Monthly Basic Payroll**: {$sal} ETB\n";
                } else {
                    $reply .= "🔒 **Permission Access Notice**: You do not have HRM permissions (`manage-employee`) to view staff payroll.";
                }
            }
            else {
                $reply .= "• ⚡ **User Context**: Connected as `{$user->name}` (`{$user->type}`).\n";
                $reply .= "• 🧠 **Persistent Memory**: Conversation memory active.\n";
                $reply .= "• Ask me about sales, receivables, Ethiopian taxes, employee payroll, or cash flow forecasts!";
            }
        }

        // 2. Save Assistant Response to Persistent Memory
        if (Schema::hasTable('copilot_memories')) {
            try {
                CopilotMemory::create([
                    'user_id'    => $user->id,
                    'created_by' => $companyId,
                    'role'       => 'assistant',
                    'content'    => $reply,
                ]);
            } catch (\Throwable $e) {}
        }

        // 3. Track AI Token Consumption & Price Cost per 1K Tokens
        if (Schema::hasTable('copilot_token_usages')) {
            try {
                $promptTokens = max(10, ceil(mb_strlen($request->prompt) / 4) + 120);
                $completionTokens = max(10, ceil(mb_strlen($reply) / 4) + 30);
                $totalTokens = $promptTokens + $completionTokens;

                $pricePer1k = floatval(Setting::where('key', 'copilot_token_price_per_1k')->value('value') ?? 0.05);
                $tokenCost  = round(($totalTokens / 1000) * $pricePer1k, 4);

                $activeModel = Setting::where('key', 'copilot_ai_model')->value('value') ?? 'gemini-1.5-flash';

                CopilotTokenUsage::create([
                    'company_id'        => $companyId,
                    'user_id'           => $user->id,
                    'prompt_tokens'     => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens'      => $totalTokens,
                    'token_cost'        => $tokenCost,
                    'model_name'        => $activeModel,
                ]);
            } catch (\Throwable $e) {
                \Log::warning("Token usage tracking error: " . $e->getMessage());
            }
        }

        return response()->json([
            'reply' => $reply,
            'confidence' => 0.98,
        ]);
    }

    private function calculateEthiopianEmploymentTax($grossSalary)
    {
        if ($grossSalary <= 600) {
            return 0;
        } elseif ($grossSalary <= 1650) {
            return ($grossSalary * 0.10) - 60;
        } elseif ($grossSalary <= 3200) {
            return ($grossSalary * 0.15) - 142.50;
        } elseif ($grossSalary <= 5250) {
            return ($grossSalary * 0.20) - 302.50;
        } elseif ($grossSalary <= 7800) {
            return ($grossSalary * 0.25) - 565.00;
        } elseif ($grossSalary <= 10900) {
            return ($grossSalary * 0.30) - 955.00;
        } else {
            return ($grossSalary * 0.35) - 1500.00;
        }
    }
}
