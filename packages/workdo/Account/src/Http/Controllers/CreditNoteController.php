<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoiceReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\ApproveCreditNote;
use Workdo\Account\Events\DestroyCreditNote;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Services\JournalService;

class CreditNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkCreditNoteAccess(CreditNote $creditNote): bool
    {
        if ($creditNote->created_by != creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-credit-notes')) {
            return true;
        } elseif (Auth::user()->can('manage-own-credit-notes')) {
            if ($creditNote->creator_id != Auth::id() && $creditNote->customer_id != Auth::id()) {
                return false;
            }
            if ($creditNote->creator_id != Auth::id() && Auth::user()->type == 'client' && $creditNote->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-credit-notes')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            $request->sort,
            ['credit_note_number', 'credit_note_date', 'total_amount', 'balance_amount', 'status', 'created_at'],
            'credit_note_date',
            $request->direction ?? 'desc'
        );

        $query = CreditNote::with(['customer', 'salesReturn', 'approvedBy'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-credit-notes')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-credit-notes')) {
                    $q->where('created_by', creatorId())
                        ->where(function ($sub) {
                            $sub->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        });
                    if (Auth::user()->type == 'client') {
                        $q->where('status', '!=', 'draft');
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where('credit_note_number', 'like', '%' . $request->search . '%');
        }
        if ($request->sales_return_id) {
            $query->where('return_id', $request->sales_return_id);
        }

        $query->orderBy($sortCol, $sortDir);

        $creditNotes = $query->paginate($request->per_page ?? 10)->withQueryString();
        $customers = User::where('type', 'client')->where('created_by', creatorId())->get(['id', 'name']);
        $salesReturns = SalesInvoiceReturn::where('created_by', creatorId())->get(['id', 'return_number']);

        return Inertia::render('Account/CreditNotes/Index', [
            'creditNotes'  => $creditNotes,
            'customers'    => $customers,
            'salesReturns' => $salesReturns,
            'filters'      => $request->only(['customer_id', 'status', 'sales_return_id']),
        ]);
    }

    public function show(CreditNote $creditNote)
    {
        if (Auth::user()->can('view-credit-notes')
            && (Auth::user()->type == 'client' ? $creditNote->customer_id == Auth::id() : $creditNote->created_by == creatorId())
        ) {
            if (!$this->checkCreditNoteAccess($creditNote)) {
                return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
            }

            $creditNote->load(['customer', 'items.product', 'items.taxes', 'salesReturn', 'applications.payment']);

            return Inertia::render('Account/CreditNotes/View', [
                'creditNote' => $creditNote,
            ]);
        }

        return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
    }

    public function approve(CreditNote $creditNote)
    {
        if (!Auth::user()->can('approve-credit-notes') || $creditNote->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        try {
            DB::transaction(function () use ($creditNote) {
                $lockedNote = CreditNote::where('id', $creditNote->id)
                    ->where('created_by', creatorId())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedNote->status !== 'draft') {
                    throw new \Exception(__('Only draft credit notes can be approved.'));
                }

                $this->journalService->createCreditNoteJournal($lockedNote);
                $this->journalService->createCreditNoteCOGSJournal($lockedNote);

                $lockedNote->update([
                    'status'      => 'approved',
                    'approved_by' => Auth::id(),
                ]);
            });

            $creditNote->refresh();
            ApproveCreditNote::dispatch($creditNote);

            return back()->with('success', __('Credit note approved successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(CreditNote $creditNote)
    {
        if (!Auth::user()->can('delete-credit-notes') || $creditNote->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($creditNote->status !== 'draft') {
            return back()->with('error', __('Only draft credit notes can be deleted.'));
        }

        DestroyCreditNote::dispatch($creditNote);
        $creditNote->delete();

        return back()->with('success', __('Credit note deleted successfully.'));
    }
}
