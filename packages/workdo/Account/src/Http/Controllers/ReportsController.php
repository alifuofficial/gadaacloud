<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Services\ReportService;

class ReportsController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    private function resolveFinancialYearRange(): array
    {
        $currentYear = date('Y');
        $range = [
            'year_start_date' => "$currentYear-01-01",
            'year_end_date'   => "$currentYear-12-31",
            'calendar_mode'   => 'gregorian',
            'fiscal_year'     => $currentYear,
        ];

        if (
            function_exists('ethiopian_calendar_active')
            && ethiopian_calendar_active()
            && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)
        ) {
            try {
                $ethSvc = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
                $ethFy = $ethSvc->getCurrentFiscalYear();
                $fyRange = $ethSvc->getFiscalYearRange($ethFy);
                $range = [
                    'year_start_date' => $fyRange['start_date'],
                    'year_end_date'   => $fyRange['end_date'],
                    'calendar_mode'   => 'ethiopian',
                    'fiscal_year'     => "EFY {$ethFy}",
                ];
            } catch (\Throwable $e) {
                // fallback to Gregorian year bounds
            }
        }

        return $range;
    }

    public function index()
    {
        if (!Auth::user()->can('manage-account-reports')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/Reports/Index', [
            'financialYear' => $this->resolveFinancialYearRange(),
        ]);
    }

    public function invoiceAging(Request $request)
    {
        if (!Auth::user()->can('view-invoice-aging') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'as_of_date' => 'nullable|date_format:Y-m-d',
        ]);

        $filters = [
            'as_of_date' => $validated['as_of_date'] ?? date('Y-m-d'),
        ];

        return response()->json($this->reportService->getInvoiceAging($filters));
    }

    public function billAging(Request $request)
    {
        if (!Auth::user()->can('view-bill-aging') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'as_of_date' => 'nullable|date_format:Y-m-d',
        ]);

        $filters = [
            'as_of_date' => $validated['as_of_date'] ?? date('Y-m-d'),
        ];

        return response()->json($this->reportService->getBillAging($filters));
    }

    public function taxSummary(Request $request)
    {
        if (!Auth::user()->can('view-tax-summary') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date'   => 'nullable|date_format:Y-m-d',
        ]);

        $fy = $this->resolveFinancialYearRange();
        $filters = [
            'from_date' => $validated['from_date'] ?? $fy['year_start_date'],
            'to_date'   => $validated['to_date'] ?? $fy['year_end_date'],
        ];

        return response()->json($this->reportService->getTaxSummary($filters));
    }

    public function incomeStatement(Request $request)
    {
        if (!Auth::user()->can('view-income-statement') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date'   => 'nullable|date_format:Y-m-d',
        ]);

        $fy = $this->resolveFinancialYearRange();
        $filters = [
            'from_date' => $validated['from_date'] ?? $fy['year_start_date'],
            'to_date'   => $validated['to_date'] ?? $fy['year_end_date'],
        ];

        return response()->json($this->reportService->getIncomeStatement($filters));
    }

    public function customerBalance(Request $request)
    {
        if (!Auth::user()->can('view-customer-balance') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'as_of_date'         => 'nullable|date_format:Y-m-d',
            'show_zero_balances' => 'nullable',
        ]);

        $filters = [
            'as_of_date'         => $validated['as_of_date'] ?? date('Y-m-d'),
            'show_zero_balances' => $request->boolean('show_zero_balances'),
        ];

        return response()->json($this->reportService->getCustomerBalanceSummary($filters));
    }

    public function vendorBalance(Request $request)
    {
        if (!Auth::user()->can('view-vendor-balance') && !Auth::user()->can('manage-account-reports')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $validated = $request->validate([
            'as_of_date'         => 'nullable|date_format:Y-m-d',
            'show_zero_balances' => 'nullable',
        ]);

        $filters = [
            'as_of_date'         => $validated['as_of_date'] ?? date('Y-m-d'),
            'show_zero_balances' => $request->boolean('show_zero_balances'),
        ];

        return response()->json($this->reportService->getVendorBalanceSummary($filters));
    }

    public function printInvoiceAging(Request $request)
    {
        if (!Auth::user()->can('print-invoice-aging')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate(['as_of_date' => 'nullable|date_format:Y-m-d']);
        $filters = ['as_of_date' => $validated['as_of_date'] ?? date('Y-m-d')];
        $data = $this->reportService->getInvoiceAging($filters);

        return Inertia::render('Account/Reports/Print/InvoiceAging', ['data' => $data, 'filters' => $filters]);
    }

    public function printBillAging(Request $request)
    {
        if (!Auth::user()->can('print-bill-aging')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate(['as_of_date' => 'nullable|date_format:Y-m-d']);
        $filters = ['as_of_date' => $validated['as_of_date'] ?? date('Y-m-d')];
        $data = $this->reportService->getBillAging($filters);

        return Inertia::render('Account/Reports/Print/BillAging', ['data' => $data, 'filters' => $filters]);
    }

    public function printTaxSummary(Request $request)
    {
        if (!Auth::user()->can('print-tax-summary')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $fy = $this->resolveFinancialYearRange();
        $filters = [
            'from_date' => $validated['from_date'] ?? $fy['year_start_date'],
            'to_date'   => $validated['to_date'] ?? $fy['year_end_date'],
        ];
        $data = $this->reportService->getTaxSummary($filters);

        return Inertia::render('Account/Reports/Print/TaxSummary', ['data' => $data, 'filters' => $filters]);
    }

    public function printIncomeStatement(Request $request)
    {
        if (!Auth::user()->can('print-income-statement') && !Auth::user()->can('manage-account-reports')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $fy = $this->resolveFinancialYearRange();
        $filters = [
            'from_date' => $validated['from_date'] ?? $fy['year_start_date'],
            'to_date'   => $validated['to_date'] ?? $fy['year_end_date'],
        ];
        $data = $this->reportService->getIncomeStatement($filters);

        return Inertia::render('Account/Reports/Print/IncomeStatement', ['data' => $data, 'filters' => $filters]);
    }

    public function printCustomerBalance(Request $request)
    {
        if (!Auth::user()->can('print-customer-balance')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate(['as_of_date' => 'nullable|date_format:Y-m-d']);
        $filters = [
            'as_of_date'         => $validated['as_of_date'] ?? date('Y-m-d'),
            'show_zero_balances' => $request->boolean('show_zero_balances'),
        ];
        $data = $this->reportService->getCustomerBalanceSummary($filters);

        return Inertia::render('Account/Reports/Print/CustomerBalance', ['data' => $data, 'filters' => $filters]);
    }

    public function printVendorBalance(Request $request)
    {
        if (!Auth::user()->can('print-vendor-balance')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate(['as_of_date' => 'nullable|date_format:Y-m-d']);
        $filters = [
            'as_of_date'         => $validated['as_of_date'] ?? date('Y-m-d'),
            'show_zero_balances' => $request->boolean('show_zero_balances'),
        ];
        $data = $this->reportService->getVendorBalanceSummary($filters);

        return Inertia::render('Account/Reports/Print/VendorBalance', ['data' => $data, 'filters' => $filters]);
    }

    public function customerDetail($customerId, Request $request)
    {
        if (!Auth::user()->can('view-customer-detail-report')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $filters = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date'   => $validated['end_date'] ?? null,
        ];

        $data = $this->reportService->getCustomerDetail($customerId, $filters);
        if (!$data) {
            return back()->with('error', __('Customer not found'));
        }

        return Inertia::render('Account/Reports/CustomerDetail', [
            'customerData' => $data,
        ]);
    }

    public function vendorDetail($vendorId, Request $request)
    {
        if (!Auth::user()->can('view-vendor-detail-report')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $filters = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date'   => $validated['end_date'] ?? null,
        ];

        $data = $this->reportService->getVendorDetail($vendorId, $filters);
        if (!$data) {
            return back()->with('error', __('Vendor not found'));
        }

        return Inertia::render('Account/Reports/VendorDetail', [
            'vendorData' => $data,
        ]);
    }

    public function printCustomerDetail($customerId, Request $request)
    {
        if (!Auth::user()->can('print-customer-detail-report')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $filters = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date'   => $validated['end_date'] ?? null,
        ];

        $data = $this->reportService->getCustomerDetail($customerId, $filters);
        if (!$data) {
            return back()->with('error', __('Customer not found'));
        }

        return Inertia::render('Account/Reports/Print/CustomerDetail', [
            'data'    => $data,
            'filters' => $filters,
        ]);
    }

    public function printVendorDetail($vendorId, Request $request)
    {
        if (!Auth::user()->can('print-vendor-detail-report')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);
        $filters = [
            'start_date' => $validated['start_date'] ?? null,
            'end_date'   => $validated['end_date'] ?? null,
        ];

        $data = $this->reportService->getVendorDetail($vendorId, $filters);
        if (!$data) {
            return back()->with('error', __('Vendor not found'));
        }

        return Inertia::render('Account/Reports/Print/VendorDetail', [
            'data'    => $data,
            'filters' => $filters,
        ]);
    }
}
