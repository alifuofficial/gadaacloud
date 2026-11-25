<?php

namespace Workdo\Account\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportService
{
    private function resolveDefaultDateRange(): array
    {
        $defaultFrom = date('Y-01-01');
        $defaultTo = date('Y-12-31');

        if (
            function_exists('ethiopian_calendar_active')
            && ethiopian_calendar_active()
            && class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)
        ) {
            try {
                $ethSvc = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
                $fyRange = $ethSvc->getFiscalYearRange($ethSvc->getCurrentFiscalYear());
                $defaultFrom = $fyRange['start_date'];
                $defaultTo = $fyRange['end_date'];
            } catch (\Throwable $e) {
                // fallback to Gregorian year bounds
            }
        }

        return [$defaultFrom, $defaultTo];
    }

    public function getInvoiceAging($filters = [])
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');
        $companyId = creatorId();

        $invoices = DB::table('sales_invoices')
            ->where('sales_invoices.created_by', $companyId)
            ->whereIn('sales_invoices.status', ['posted', 'partial'])
            ->leftJoin('users', function ($join) use ($companyId) {
                $join->on('sales_invoices.customer_id', '=', 'users.id')
                    ->where('users.created_by', '=', $companyId);
            })
            ->where('users.type', 'client')
            ->where('sales_invoices.balance_amount', '>', 0)
            ->select(
                'sales_invoices.id',
                'sales_invoices.invoice_number',
                'sales_invoices.due_date',
                'sales_invoices.balance_amount as balance',
                'users.name as customer_name',
                'users.id as customer_id'
            )
            ->selectRaw('DATEDIFF(?, sales_invoices.due_date) as days_overdue', [$asOfDate])
            ->get();

        $aging = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
            'total' => 0,
        ];

        $customerData = [];

        foreach ($invoices as $invoice) {
            $balance = (float) $invoice->balance;
            $days = (int) $invoice->days_overdue;

            if ($days <= 0) {
                $bucket = 'current';
            } elseif ($days <= 30) {
                $bucket = '1_30_days';
            } elseif ($days <= 60) {
                $bucket = '31_60_days';
            } elseif ($days <= 90) {
                $bucket = '61_90_days';
            } else {
                $bucket = 'over_90_days';
            }

            $aging[$bucket] += $balance;
            $aging['total'] += $balance;

            if (!isset($customerData[$invoice->customer_id])) {
                $customerData[$invoice->customer_id] = [
                    'customer_name' => $invoice->customer_name,
                    'current' => 0,
                    '1_30_days' => 0,
                    '31_60_days' => 0,
                    '61_90_days' => 0,
                    'over_90_days' => 0,
                    'total' => 0,
                ];
            }

            $customerData[$invoice->customer_id][$bucket] += $balance;
            $customerData[$invoice->customer_id]['total'] += $balance;
        }

        return [
            'aging_summary' => $aging,
            'customers' => array_values($customerData),
            'as_of_date' => $asOfDate,
        ];
    }

    public function getBillAging($filters = [])
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');
        $companyId = creatorId();

        $bills = DB::table('purchase_invoices')
            ->where('purchase_invoices.created_by', $companyId)
            ->whereIn('purchase_invoices.status', ['posted', 'partial'])
            ->leftJoin('users', function ($join) use ($companyId) {
                $join->on('purchase_invoices.vendor_id', '=', 'users.id')
                    ->where('users.created_by', '=', $companyId);
            })
            ->where('users.type', 'vendor')
            ->where('purchase_invoices.balance_amount', '>', 0)
            ->select(
                'purchase_invoices.id',
                'purchase_invoices.invoice_number as bill_number',
                'purchase_invoices.due_date',
                'purchase_invoices.balance_amount as balance',
                'users.name as vendor_name',
                'users.id as vendor_id'
            )
            ->selectRaw('DATEDIFF(?, purchase_invoices.due_date) as days_overdue', [$asOfDate])
            ->get();

        $aging = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
            'total' => 0,
        ];

        $vendorData = [];

        foreach ($bills as $bill) {
            $balance = (float) $bill->balance;
            $days = (int) $bill->days_overdue;

            if ($days <= 0) {
                $bucket = 'current';
            } elseif ($days <= 30) {
                $bucket = '1_30_days';
            } elseif ($days <= 60) {
                $bucket = '31_60_days';
            } elseif ($days <= 90) {
                $bucket = '61_90_days';
            } else {
                $bucket = 'over_90_days';
            }

            $aging[$bucket] += $balance;
            $aging['total'] += $balance;

            if (!isset($vendorData[$bill->vendor_id])) {
                $vendorData[$bill->vendor_id] = [
                    'vendor_name' => $bill->vendor_name,
                    'current' => 0,
                    '1_30_days' => 0,
                    '31_60_days' => 0,
                    '61_90_days' => 0,
                    'over_90_days' => 0,
                    'total' => 0,
                ];
            }

            $vendorData[$bill->vendor_id][$bucket] += $balance;
            $vendorData[$bill->vendor_id]['total'] += $balance;
        }

        return [
            'aging_summary' => $aging,
            'vendors' => array_values($vendorData),
            'as_of_date' => $asOfDate,
        ];
    }

    public function getTaxSummary($filters = [])
    {
        [$defaultFrom, $defaultTo] = $this->resolveDefaultDateRange();
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : $defaultFrom;
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : $defaultTo;
        $companyId = creatorId();

        // Output VAT collected on sales invoices
        $taxCollected = DB::table('sales_invoice_item_taxes')
            ->join('sales_invoice_items', 'sales_invoice_item_taxes.item_id', '=', 'sales_invoice_items.id')
            ->join('sales_invoices', 'sales_invoice_items.invoice_id', '=', 'sales_invoices.id')
            ->where('sales_invoices.created_by', $companyId)
            ->whereIn('sales_invoices.status', ['posted', 'partial', 'paid'])
            ->whereBetween('sales_invoices.invoice_date', [$fromDate, $toDate])
            ->select(
                'sales_invoice_item_taxes.tax_name',
                'sales_invoice_item_taxes.tax_rate',
                DB::raw('SUM((sales_invoice_items.unit_price * sales_invoice_items.quantity - sales_invoice_items.discount_amount) * sales_invoice_item_taxes.tax_rate / 100) as tax_amount')
            )
            ->groupBy('sales_invoice_item_taxes.tax_name', 'sales_invoice_item_taxes.tax_rate')
            ->get();

        // POS Output Tax (if Pos module table exists)
        $posTaxTotal = 0.0;
        if (Schema::hasTable('pos_items') && Schema::hasTable('pos')) {
            $posTaxTotal = (float) DB::table('pos_items')
                ->join('pos', 'pos_items.pos_id', '=', 'pos.id')
                ->where('pos.created_by', $companyId)
                ->whereBetween(DB::raw('COALESCE(pos.pos_date, DATE(pos.created_at))'), [$fromDate, $toDate])
                ->sum('pos_items.tax_amount');
        }

        // Credit Note Output VAT adjustments (reduces Output VAT)
        $creditNoteTax = DB::table('credit_note_item_taxes')
            ->join('credit_note_items', 'credit_note_item_taxes.item_id', '=', 'credit_note_items.id')
            ->join('credit_notes', 'credit_note_items.credit_note_id', '=', 'credit_notes.id')
            ->where('credit_notes.created_by', $companyId)
            ->whereIn('credit_notes.status', ['approved', 'partial', 'applied', 'completed'])
            ->whereBetween('credit_notes.credit_note_date', [$fromDate, $toDate])
            ->sum(DB::raw('(credit_note_items.unit_price * credit_note_items.quantity - credit_note_items.discount_amount) * credit_note_item_taxes.tax_rate / 100'));

        // Input VAT paid on purchase bills
        $taxPaid = DB::table('purchase_invoice_item_taxes')
            ->join('purchase_invoice_items', 'purchase_invoice_item_taxes.item_id', '=', 'purchase_invoice_items.id')
            ->join('purchase_invoices', 'purchase_invoice_items.invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_invoices.created_by', $companyId)
            ->whereIn('purchase_invoices.status', ['posted', 'partial', 'paid'])
            ->whereBetween('purchase_invoices.invoice_date', [$fromDate, $toDate])
            ->select(
                'purchase_invoice_item_taxes.tax_name',
                'purchase_invoice_item_taxes.tax_rate',
                DB::raw('SUM((purchase_invoice_items.unit_price * purchase_invoice_items.quantity - purchase_invoice_items.discount_amount) * purchase_invoice_item_taxes.tax_rate / 100) as tax_amount')
            )
            ->groupBy('purchase_invoice_item_taxes.tax_name', 'purchase_invoice_item_taxes.tax_rate')
            ->get();

        // Debit Note Input VAT adjustments (reduces Input VAT)
        $debitNoteTax = DB::table('debit_note_item_taxes')
            ->join('debit_note_items', 'debit_note_item_taxes.item_id', '=', 'debit_note_items.id')
            ->join('debit_notes', 'debit_note_items.debit_note_id', '=', 'debit_notes.id')
            ->where('debit_notes.created_by', $companyId)
            ->whereIn('debit_notes.status', ['approved', 'partial', 'applied', 'completed'])
            ->whereBetween('debit_notes.debit_note_date', [$fromDate, $toDate])
            ->sum(DB::raw('(debit_note_items.unit_price * debit_note_items.quantity - debit_note_items.discount_amount) * debit_note_item_taxes.tax_rate / 100'));

        // Ethiopian Withholding Tax (2% WHT) summary
        $whtPayable = 0.0;
        $whtReceivable = 0.0;
        if (Schema::hasColumn('vendor_payments', 'wht_amount')) {
            $whtPayable = (float) DB::table('vendor_payments')
                ->where('created_by', $companyId)
                ->where('status', 'cleared')
                ->whereBetween('payment_date', [$fromDate, $toDate])
                ->sum('wht_amount');
        }
        if (Schema::hasColumn('customer_payments', 'wht_amount')) {
            $whtReceivable = (float) DB::table('customer_payments')
                ->where('created_by', $companyId)
                ->where('status', 'cleared')
                ->whereBetween('payment_date', [$fromDate, $toDate])
                ->sum('wht_amount');
        }

        $collectedItems = $taxCollected->map(fn ($t) => [
            'tax_name' => $t->tax_name . ' (' . $t->tax_rate . '%)',
            'amount' => round((float) $t->tax_amount, 2),
        ])->values()->all();

        if ($posTaxTotal > 0.005) {
            $collectedItems[] = [
                'tax_name' => __('POS Sales Tax Collected'),
                'amount' => round($posTaxTotal, 2),
            ];
        }
        if ((float) $creditNoteTax > 0.005) {
            $collectedItems[] = [
                'tax_name' => __('Credit Note VAT Adjustments (-)'),
                'amount' => -round((float) $creditNoteTax, 2),
            ];
        }

        $paidItems = $taxPaid->map(fn ($t) => [
            'tax_name' => $t->tax_name . ' (' . $t->tax_rate . '%)',
            'amount' => round((float) $t->tax_amount, 2),
        ])->values()->all();

        if ((float) $debitNoteTax > 0.005) {
            $paidItems[] = [
                'tax_name' => __('Debit Note VAT Adjustments (-)'),
                'amount' => -round((float) $debitNoteTax, 2),
            ];
        }

        $totalCollected = round((float) $taxCollected->sum('tax_amount') + $posTaxTotal - (float) $creditNoteTax, 2);
        $totalPaid = round((float) $taxPaid->sum('tax_amount') - (float) $debitNoteTax, 2);

        return [
            'tax_collected' => [
                'items' => $collectedItems,
                'total' => $totalCollected,
            ],
            'tax_paid' => [
                'items' => $paidItems,
                'total' => $totalPaid,
            ],
            'wht_summary' => [
                'wht_withheld_from_vendors' => round($whtPayable, 2),
                'wht_deducted_by_customers' => round($whtReceivable, 2),
                'net_wht_remittance_due' => round($whtPayable - $whtReceivable, 2),
            ],
            'net_tax_liability' => round($totalCollected - $totalPaid, 2),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    public function getIncomeStatement($filters = [])
    {
        [$defaultFrom, $defaultTo] = $this->resolveDefaultDateRange();
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : $defaultFrom;
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : $defaultTo;
        $companyId = creatorId();

        $rows = DB::table('journal_entry_items as jei')
            ->join('journal_entries as je', 'jei.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as coa', 'jei.account_id', '=', 'coa.id')
            ->join('account_types as at', 'coa.account_type_id', '=', 'at.id')
            ->join('account_categories as ac', 'at.category_id', '=', 'ac.id')
            ->where('je.created_by', $companyId)
            ->where('coa.created_by', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.journal_date', [$fromDate, $toDate])
            ->whereIn('ac.type', ['revenue', 'expenses'])
            ->select(
                'ac.type as category_type',
                'at.name as type_name',
                'coa.account_code',
                'coa.account_name',
                DB::raw('SUM(jei.credit_amount) as credits'),
                DB::raw('SUM(jei.debit_amount) as debits')
            )
            ->groupBy('ac.type', 'at.name', 'coa.account_code', 'coa.account_name')
            ->orderBy('coa.account_code')
            ->get();

        $revenue = [];
        $expenses = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            if ($row->category_type === 'revenue') {
                $amount = round((float) $row->credits - (float) $row->debits, 2);
                if (abs($amount) >= 0.01) {
                    $revenue[] = [
                        'category' => "{$row->account_code} - {$row->account_name}",
                        'amount' => $amount,
                    ];
                    $totalRevenue += $amount;
                }
            } else {
                $amount = round((float) $row->debits - (float) $row->credits, 2);
                if (abs($amount) >= 0.01) {
                    $expenses[] = [
                        'category' => "{$row->account_code} - {$row->account_name}",
                        'amount' => $amount,
                    ];
                    $totalExpenses += $amount;
                }
            }
        }

        $totalRevenue = round($totalRevenue, 2);
        $totalExpenses = round($totalExpenses, 2);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => round($totalRevenue - $totalExpenses, 2),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    public function getCustomerBalanceSummary($filters = [])
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');
        $showZeroBalances = $filters['show_zero_balances'] ?? false;
        $companyId = creatorId();

        $customers = DB::table('users')
            ->where('created_by', $companyId)
            ->where('type', 'client')
            ->select('id', 'name', 'email')
            ->get();

        $balances = [];
        $totalBalance = 0;

        foreach ($customers as $customer) {
            $invoiced = DB::table('sales_invoices')
                ->where('created_by', $companyId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['posted', 'partial', 'paid'])
                ->where('invoice_date', '<=', $asOfDate)
                ->sum('total_amount');

            $returns = DB::table('sales_invoice_returns')
                ->where('created_by', $companyId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['approved', 'completed'])
                ->where('return_date', '<=', $asOfDate)
                ->sum('total_amount');

            $balance = DB::table('sales_invoices')
                ->where('created_by', $companyId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['posted', 'partial', 'paid'])
                ->where('invoice_date', '<=', $asOfDate)
                ->sum('balance_amount');

            $netInvoiced = $invoiced - $returns;
            $paid = $invoiced - $balance;

            if (!$showZeroBalances && abs($balance) < 0.01) {
                continue;
            }

            $balances[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'total_invoiced' => $invoiced,
                'total_returns' => $returns,
                'net_invoiced' => $netInvoiced,
                'total_paid' => $paid,
                'balance' => $balance,
            ];

            $totalBalance += $balance;
        }

        usort($balances, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return [
            'customers' => $balances,
            'total_balance' => $totalBalance,
            'as_of_date' => $asOfDate,
        ];
    }

    public function getVendorBalanceSummary($filters = [])
    {
        $asOfDate = $filters['as_of_date'] ?? date('Y-m-d');
        $showZeroBalances = $filters['show_zero_balances'] ?? false;
        $companyId = creatorId();

        $vendors = DB::table('users')
            ->where('created_by', $companyId)
            ->where('type', 'vendor')
            ->select('id', 'name', 'email')
            ->get();

        $balances = [];
        $totalBalance = 0;

        foreach ($vendors as $vendor) {
            $billed = DB::table('purchase_invoices')
                ->where('created_by', $companyId)
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', ['posted', 'partial', 'paid'])
                ->where('invoice_date', '<=', $asOfDate)
                ->sum('total_amount');

            $returns = DB::table('purchase_returns')
                ->where('created_by', $companyId)
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', ['approved', 'completed'])
                ->where('return_date', '<=', $asOfDate)
                ->sum('total_amount');

            $balance = DB::table('purchase_invoices')
                ->where('created_by', $companyId)
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', ['posted', 'partial', 'paid'])
                ->where('invoice_date', '<=', $asOfDate)
                ->sum('balance_amount');

            $netBilled = $billed - $returns;
            $paid = $billed - $balance;

            if (!$showZeroBalances && abs($balance) < 0.01) {
                continue;
            }

            $balances[] = [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'vendor_email' => $vendor->email,
                'total_billed' => $billed,
                'total_returns' => $returns,
                'net_billed' => $netBilled,
                'total_paid' => $paid,
                'balance' => $balance,
            ];

            $totalBalance += $balance;
        }

        usort($balances, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return [
            'vendors' => $balances,
            'total_balance' => $totalBalance,
            'as_of_date' => $asOfDate,
        ];
    }

    public function getCustomerDetail($customerId, $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $companyId = creatorId();

        $customer = DB::table('users')
            ->where('id', $customerId)
            ->where('created_by', $companyId)
            ->where('type', 'client')
            ->select('id', 'name', 'email')
            ->first();

        if (!$customer) {
            return null;
        }

        $invoicesQuery = DB::table('sales_invoices')
            ->where('created_by', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['posted', 'partial', 'paid'])
            ->select('invoice_number', 'invoice_date as date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'balance_amount', 'status');

        if ($startDate) {
            $invoicesQuery->where('invoice_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoicesQuery->where('invoice_date', '<=', $endDate);
        }
        $invoices = $invoicesQuery->orderBy('invoice_date', 'desc')->get();

        $returnsQuery = DB::table('sales_invoice_returns')
            ->where('created_by', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'completed'])
            ->select('return_number', 'return_date as date', 'subtotal', 'tax_amount', 'total_amount', 'status');

        if ($startDate) {
            $returnsQuery->where('return_date', '>=', $startDate);
        }
        if ($endDate) {
            $returnsQuery->where('return_date', '<=', $endDate);
        }
        $returns = $returnsQuery->orderBy('return_date', 'desc')->get();

        $creditNotesQuery = DB::table('credit_notes')
            ->where('created_by', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'partial', 'applied', 'completed'])
            ->select('credit_note_number', 'credit_note_date as date', 'total_amount', 'applied_amount', 'balance_amount', 'status');

        if ($startDate) {
            $creditNotesQuery->where('credit_note_date', '>=', $startDate);
        }
        if ($endDate) {
            $creditNotesQuery->where('credit_note_date', '<=', $endDate);
        }
        $creditNotes = $creditNotesQuery->orderBy('credit_note_date', 'desc')->get();

        $paymentsQuery = DB::table('customer_payments')
            ->leftJoin('bank_accounts', 'customer_payments.bank_account_id', '=', 'bank_accounts.id')
            ->where('customer_payments.created_by', $companyId)
            ->where('customer_payments.customer_id', $customerId)
            ->select('customer_payments.payment_number', 'customer_payments.payment_date as date', 'customer_payments.payment_amount as amount', 'customer_payments.reference_number', 'customer_payments.status', 'bank_accounts.account_name as bank_account');

        if ($startDate) {
            $paymentsQuery->where('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $paymentsQuery->where('payment_date', '<=', $endDate);
        }
        $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();

        return [
            'customer' => $customer,
            'date_range' => ['start_date' => $startDate, 'end_date' => $endDate],
            'invoices' => $invoices,
            'returns' => $returns,
            'credit_notes' => $creditNotes,
            'payments' => $payments,
            'summary' => [
                'total_invoiced' => $invoices->sum('total_amount'),
                'total_returns' => $returns->sum('total_amount'),
                'total_credit_notes' => $creditNotes->sum('total_amount'),
                'total_payments' => $payments->sum('amount'),
                'balance' => $invoices->sum('balance_amount'),
            ],
        ];
    }

    public function getVendorDetail($vendorId, $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $companyId = creatorId();

        $vendor = DB::table('users')
            ->where('id', $vendorId)
            ->where('created_by', $companyId)
            ->where('type', 'vendor')
            ->select('id', 'name', 'email')
            ->first();

        if (!$vendor) {
            return null;
        }

        $invoicesQuery = DB::table('purchase_invoices')
            ->where('created_by', $companyId)
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['posted', 'partial', 'paid'])
            ->select('invoice_number', 'invoice_date as date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'balance_amount', 'status');

        if ($startDate) {
            $invoicesQuery->where('invoice_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoicesQuery->where('invoice_date', '<=', $endDate);
        }
        $invoices = $invoicesQuery->orderBy('invoice_date', 'desc')->get();

        $returnsQuery = DB::table('purchase_returns')
            ->where('created_by', $companyId)
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['approved', 'completed'])
            ->select('return_number', 'return_date as date', 'subtotal', 'tax_amount', 'total_amount', 'status');

        if ($startDate) {
            $returnsQuery->where('return_date', '>=', $startDate);
        }
        if ($endDate) {
            $returnsQuery->where('return_date', '<=', $endDate);
        }
        $returns = $returnsQuery->orderBy('return_date', 'desc')->get();

        $debitNotesQuery = DB::table('debit_notes')
            ->where('created_by', $companyId)
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['approved', 'partial', 'applied', 'completed'])
            ->select('debit_note_number', 'debit_note_date as date', 'total_amount', 'applied_amount', 'balance_amount', 'status');

        if ($startDate) {
            $debitNotesQuery->where('debit_note_date', '>=', $startDate);
        }
        if ($endDate) {
            $debitNotesQuery->where('debit_note_date', '<=', $endDate);
        }
        $debitNotes = $debitNotesQuery->orderBy('debit_note_date', 'desc')->get();

        $paymentsQuery = DB::table('vendor_payments')
            ->leftJoin('bank_accounts', 'vendor_payments.bank_account_id', '=', 'bank_accounts.id')
            ->where('vendor_payments.created_by', $companyId)
            ->where('vendor_payments.vendor_id', $vendorId)
            ->select('vendor_payments.payment_number', 'vendor_payments.payment_date as date', 'vendor_payments.payment_amount as amount', 'vendor_payments.reference_number', 'vendor_payments.status', 'bank_accounts.account_name as bank_account');

        if ($startDate) {
            $paymentsQuery->where('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $paymentsQuery->where('payment_date', '<=', $endDate);
        }
        $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();

        return [
            'vendor' => $vendor,
            'date_range' => ['start_date' => $startDate, 'end_date' => $endDate],
            'invoices' => $invoices,
            'returns' => $returns,
            'debit_notes' => $debitNotes,
            'payments' => $payments,
            'summary' => [
                'total_invoiced' => $invoices->sum('total_amount'),
                'total_returns' => $returns->sum('total_amount'),
                'total_debit_notes' => $debitNotes->sum('total_amount'),
                'total_payments' => $payments->sum('amount'),
                'balance' => $invoices->sum('balance_amount'),
            ],
        ];
    }
}
