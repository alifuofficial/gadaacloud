<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\DoubleEntry\Models\BalanceSheet;
use Workdo\DoubleEntry\Models\BalanceSheetNote;
use Workdo\DoubleEntry\Models\ComparativeBalanceSheet;
use Workdo\DoubleEntry\Services\BalanceSheetService;
use Workdo\DoubleEntry\Events\CreateBalanceSheet;
use Workdo\DoubleEntry\Events\FinalizeBalanceSheet;
use Workdo\DoubleEntry\Events\DestroyBalanceSheet;
use Workdo\DoubleEntry\Events\CreateBalanceSheetNote;
use Workdo\DoubleEntry\Events\DestroyBalanceSheetNote;
use Workdo\DoubleEntry\Events\YearEndClose;
use Workdo\DoubleEntry\Http\Requests\StoreBalanceSheetRequest;
use Workdo\DoubleEntry\Http\Requests\YearEndCloseRequest;
use Workdo\DoubleEntry\Http\Requests\CompareBalanceSheetRequest;
use Workdo\DoubleEntry\Http\Requests\StoreBalanceSheetNoteRequest;
use Workdo\DoubleEntry\Traits\DoubleEntryControllerHelpers;

class BalanceSheetController extends Controller
{
    use DoubleEntryControllerHelpers;

    protected $balanceSheetService;

    public function __construct(BalanceSheetService $balanceSheetService)
    {
        $this->balanceSheetService = $balanceSheetService;
    }

    public function index(Request $request)
    {
        if (Auth::user()->can('manage-balance-sheets')) {
            if (Auth::user()->can('view-balance-sheets')) {
                $latestBalanceSheet = BalanceSheet::where('created_by', creatorId())
                    ->latest('balance_sheet_date')
                    ->first();

                if ($latestBalanceSheet) {
                    return redirect()->route('double-entry.balance-sheets.show', $latestBalanceSheet->id);
                }
            }
            return redirect()->route('double-entry.balance-sheets.list');
        }

        return back()->with('error', __('Permission denied'));
    }

    public function list(Request $request)
    {
        if (Auth::user()->can('manage-balance-sheets')) {
            $columnMap = [
                'balance_sheet_date' => 'balance_sheet_date',
                'financial_year' => 'financial_year',
                'ethiopian_fiscal_year' => 'ethiopian_fiscal_year',
                'total_assets' => 'total_assets',
                'total_liabilities' => 'total_liabilities',
                'total_equity' => 'total_equity',
                'is_balanced' => 'is_balanced',
                'status' => 'status',
                'created_at' => 'created_at',
            ];

            $query = BalanceSheet::query()
                ->where('created_by', creatorId())
                ->when(request('financial_year'), fn($q) => $q->where('financial_year', request('financial_year')))
                ->when(request('status'), fn($q) => $q->where('status', request('status')));

            $query = $this->applySafeSort($query, $columnMap, 'balance_sheet_date');

            $balanceSheets = $query->paginate($this->safePerPage(10, 100))->withQueryString();

            return Inertia::render('DoubleEntry/BalanceSheets/Index', [
                'balanceSheets' => $balanceSheets,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function store(StoreBalanceSheetRequest $request)
    {
        if (Auth::user()->can('create-balance-sheets')) {
            try {
                $validated = $request->validated();
                $balanceSheetId = $this->balanceSheetService->generateBalanceSheet(
                    $validated['balance_sheet_date'],
                    $validated['financial_year'],
                    $validated['ethiopian_fiscal_year'] ?? null,
                    $validated['ethiopian_quarter'] ?? null
                );

                $balanceSheet = BalanceSheet::where('created_by', creatorId())->findOrFail($balanceSheetId);
                CreateBalanceSheet::dispatch($request, $balanceSheet);

                return redirect()->route('double-entry.balance-sheets.show', $balanceSheetId)
                    ->with('success', __('Balance sheet generated successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return back()->with('error', __('Permission denied'));
    }

    public function show($id)
    {
        if (Auth::user()->can('view-balance-sheets')) {
            $balanceSheet = BalanceSheet::with(['items.account', 'notes'])
                ->where('id', $id)
                ->where('created_by', creatorId())
                ->firstOrFail();

            // Group items by section
            $groupedItems = $balanceSheet->items->groupBy(['section_type', 'sub_section']);

            // Get all balance sheets for current tenant
            $allBalanceSheets = BalanceSheet::where('created_by', creatorId())
                ->select('id', 'balance_sheet_date', 'financial_year', 'ethiopian_fiscal_year')
                ->orderBy('balance_sheet_date', 'desc')
                ->get();

            // Get other finalized balance sheets for comparison
            $otherBalanceSheets = BalanceSheet::where('created_by', creatorId())
                ->where('id', '!=', $id)
                ->where('status', 'finalized')
                ->select('id', 'balance_sheet_date', 'financial_year', 'ethiopian_fiscal_year')
                ->orderBy('balance_sheet_date', 'desc')
                ->get();

            return Inertia::render('DoubleEntry/BalanceSheets/View', [
                'balanceSheet' => $balanceSheet,
                'groupedItems' => $groupedItems,
                'allBalanceSheets' => $allBalanceSheets,
                'otherBalanceSheets' => $otherBalanceSheets,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function finalize(Request $request, $id)
    {
        if (Auth::user()->can('finalize-balance-sheets')) {
            try {
                // Ensure tenant owns balance sheet prior to finalizing
                $balanceSheet = BalanceSheet::where('id', $id)
                    ->where('created_by', creatorId())
                    ->firstOrFail();

                $finalizedSheet = $this->balanceSheetService->finalizeBalanceSheet($balanceSheet->id);
                FinalizeBalanceSheet::dispatch($finalizedSheet);
                return back()->with('success', __('Balance sheet finalized successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return back()->with('error', __('Permission denied'));
    }

    public function destroy($id)
    {
        if (Auth::user()->can('delete-balance-sheets')) {
            $balanceSheet = BalanceSheet::where('id', $id)
                ->where('created_by', creatorId())
                ->where('status', 'draft')
                ->firstOrFail();

            DB::transaction(function () use ($balanceSheet) {
                DestroyBalanceSheet::dispatch($balanceSheet);
                $balanceSheet->items()->delete();
                $balanceSheet->notes()->delete();
                $balanceSheet->delete();
            });

            return back()->with('success', __('Balance sheet deleted successfully.'));
        }

        return back()->with('error', __('Permission denied'));
    }

    public function addNote(StoreBalanceSheetNoteRequest $request, $id)
    {
        if (Auth::user()->can('create-balance-sheet-notes')) {
            // IDOR Protection: verify parent balance sheet belongs to tenant
            $balanceSheet = BalanceSheet::where('id', $id)
                ->where('created_by', creatorId())
                ->firstOrFail();

            $validated = $request->validated();

            $note = DB::transaction(function () use ($balanceSheet, $validated) {
                $nextNoteNumber = (int) BalanceSheetNote::where('balance_sheet_id', $balanceSheet->id)
                    ->where('created_by', creatorId())
                    ->max('note_number') + 1;

                $newNote = new BalanceSheetNote();
                $newNote->balance_sheet_id = $balanceSheet->id;
                $newNote->note_number = $nextNoteNumber;
                $newNote->note_title = $validated['note_title'];
                $newNote->note_content = $validated['note_content'];
                $newNote->creator_id = Auth::id();
                $newNote->created_by = creatorId();
                $newNote->save();

                return $newNote;
            });

            CreateBalanceSheetNote::dispatch($request, $note);

            return back()->with('success', __('Note added successfully.'));
        }

        return back()->with('error', __('Permission denied'));
    }

    public function deleteNote($balanceSheetId, $noteId)
    {
        if (Auth::user()->can('delete-balance-sheet-notes')) {
            // IDOR Protection: verify parent balance sheet and note belong to tenant
            $balanceSheet = BalanceSheet::where('id', $balanceSheetId)
                ->where('created_by', creatorId())
                ->firstOrFail();

            $note = BalanceSheetNote::where('id', $noteId)
                ->where('balance_sheet_id', $balanceSheet->id)
                ->where('created_by', creatorId())
                ->firstOrFail();

            DestroyBalanceSheetNote::dispatch($note);
            $note->delete();

            return back()->with('success', __('Note deleted successfully.'));
        }

        return back()->with('error', __('Permission denied'));
    }

    public function compare(CompareBalanceSheetRequest $request)
    {
        if (Auth::user()->can('create-balance-sheet-comparisons')) {
            $validated = $request->validated();

            // IDOR Protection: double verify both periods belong to tenant
            $current = BalanceSheet::where('id', $validated['current_period_id'])
                ->where('created_by', creatorId())
                ->firstOrFail();

            $previous = BalanceSheet::where('id', $validated['previous_period_id'])
                ->where('created_by', creatorId())
                ->firstOrFail();

            $comparison = ComparativeBalanceSheet::create([
                'current_period_id' => $current->id,
                'previous_period_id' => $previous->id,
                'comparison_date' => now(),
                'ethiopian_fiscal_year' => $current->ethiopian_fiscal_year ?? null,
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            return redirect()->route('double-entry.balance-sheets.comparison', $comparison->id);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function comparisons()
    {
        if (Auth::user()->can('view-balance-sheet-comparisons')) {
            $columnMap = [
                'comparison_date' => 'comparison_date',
                'created_at' => 'created_at',
                'id' => 'id',
            ];

            $query = ComparativeBalanceSheet::query()
                ->with([
                    'currentPeriod:id,balance_sheet_date,financial_year,ethiopian_fiscal_year',
                    'previousPeriod:id,balance_sheet_date,financial_year,ethiopian_fiscal_year'
                ])
                ->where('created_by', creatorId());

            $query = $this->applySafeSort($query, $columnMap, 'comparison_date');

            $comparisons = $query->paginate($this->safePerPage(15, 100))->withQueryString();

            return Inertia::render('DoubleEntry/BalanceSheets/Comparisons', [
                'comparisons' => $comparisons,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function showComparison($id)
    {
        if (Auth::user()->can('view-balance-sheets')) {
            // IDOR Protection: enforce tenant filter
            $comparison = ComparativeBalanceSheet::with([
                'currentPeriod.items.account',
                'previousPeriod.items.account'
            ])
            ->where('id', $id)
            ->where('created_by', creatorId())
            ->firstOrFail();

            return Inertia::render('DoubleEntry/BalanceSheets/Comparison', [
                'comparison' => $comparison,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function yearEndClose(YearEndCloseRequest $request)
    {
        if (Auth::user()->can('year-end-close')) {
            try {
                $validated = $request->validated();
                $this->balanceSheetService->performYearEndClose(
                    $validated['financial_year'],
                    $validated['closing_date']
                );

                YearEndClose::dispatch($request, $validated['financial_year'], $validated['closing_date']);

                return back()->with('success', __('Year-end closing completed successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return back()->with('error', __('Permission denied'));
    }

    public function print($id)
    {
        if (Auth::user()->can('print-balance-sheets')) {
            $balanceSheet = BalanceSheet::with(['items.account'])
                ->where('id', $id)
                ->where('created_by', creatorId())
                ->firstOrFail();

            $groupedItems = $balanceSheet->items->groupBy(['section_type', 'sub_section']);

            return Inertia::render('DoubleEntry/BalanceSheets/Print', [
                'balanceSheet' => $balanceSheet,
                'groupedItems' => $groupedItems,
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    public function comparisonPrint(Request $request)
    {
        if (Auth::user()->can('print-balance-sheets')) {
            $currentId = $request->get('current_id');
            $previousId = $request->get('previous_id');

            $currentPeriod = BalanceSheet::with(['items.account'])
                ->where('id', $currentId)
                ->where('created_by', creatorId())
                ->firstOrFail();

            $previousPeriod = BalanceSheet::with(['items.account'])
                ->where('id', $previousId)
                ->where('created_by', creatorId())
                ->firstOrFail();

            return Inertia::render('DoubleEntry/BalanceSheets/ComparisonPrint', [
                'comparison' => [
                    'currentPeriod' => $currentPeriod,
                    'previousPeriod' => $previousPeriod,
                ],
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }
}
