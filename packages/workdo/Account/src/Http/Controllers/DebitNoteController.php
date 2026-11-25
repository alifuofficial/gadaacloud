<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\ApproveDebitNote;
use Workdo\Account\Events\DestroyDebitNote;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Services\JournalService;

class DebitNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkDebitNoteAccess(DebitNote $debitNote): bool
    {
        if ($debitNote->created_by != creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-debit-notes')) {
            return true;
        } elseif (Auth::user()->can('manage-own-debit-notes')) {
            if ($debitNote->creator_id != Auth::id() && $debitNote->vendor_id != Auth::id()) {
                return false;
            }
            if ($debitNote->creator_id != Auth::id() && Auth::user()->type == 'vendor' && $debitNote->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-debit-notes')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            $request->sort,
            ['debit_note_number', 'debit_note_date', 'total_amount', 'balance_amount', 'status', 'created_at'],
            'debit_note_date',
            $request->direction ?? 'desc'
        );

        $query = DebitNote::with(['vendor', 'purchaseReturn', 'approvedBy'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-debit-notes')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-debit-notes')) {
                    $q->where('created_by', creatorId())
                        ->where(function ($sub) {
                            $sub->where('creator_id', Auth::id())->orWhere('vendor_id', Auth::id());
                        });
                    if (Auth::user()->type == 'vendor') {
                        $q->where('status', '!=', 'draft');
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($request->vendor_id) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where('debit_note_number', 'like', '%' . $request->search . '%');
        }
        if ($request->purchase_return_id) {
            $query->where('return_id', $request->purchase_return_id);
        }

        $query->orderBy($sortCol, $sortDir);

        $debitNotes = $query->paginate($request->per_page ?? 10)->withQueryString();
        $vendors = User::where('type', 'vendor')->where('created_by', creatorId())->get(['id', 'name']);
        $purchaseReturns = PurchaseReturn::where('created_by', creatorId())->get(['id', 'return_number']);

        return Inertia::render('Account/DebitNotes/Index', [
            'debitNotes'      => $debitNotes,
            'vendors'         => $vendors,
            'purchaseReturns' => $purchaseReturns,
            'filters'         => $request->only(['vendor_id', 'status', 'purchase_return_id']),
        ]);
    }

    public function show(DebitNote $debitNote)
    {
        if (Auth::user()->can('view-debit-notes')
            && (Auth::user()->type == 'vendor' ? $debitNote->vendor_id == Auth::id() : $debitNote->created_by == creatorId())
        ) {
            if (!$this->checkDebitNoteAccess($debitNote)) {
                return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
            }

            $debitNote->load(['vendor', 'items.product', 'items.taxes', 'purchaseReturn', 'applications.payment']);

            return Inertia::render('Account/DebitNotes/View', [
                'debitNote' => $debitNote,
            ]);
        }

        return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
    }

    public function approve(DebitNote $debitNote)
    {
        if (!Auth::user()->can('approve-debit-notes') || $debitNote->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        try {
            DB::transaction(function () use ($debitNote) {
                $lockedNote = DebitNote::where('id', $debitNote->id)
                    ->where('created_by', creatorId())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedNote->status !== 'draft') {
                    throw new \Exception(__('Only draft debit notes can be approved.'));
                }

                $this->journalService->createDebitNoteJournal($lockedNote);

                $lockedNote->update([
                    'status'      => 'approved',
                    'approved_by' => Auth::id(),
                ]);
            });

            $debitNote->refresh();
            ApproveDebitNote::dispatch($debitNote);

            return back()->with('success', __('Debit note approved successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(DebitNote $debitNote)
    {
        if (!Auth::user()->can('delete-debit-notes') || $debitNote->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($debitNote->status !== 'draft') {
            return back()->with('error', __('Only draft debit notes can be deleted.'));
        }

        DestroyDebitNote::dispatch($debitNote);
        $debitNote->delete();

        return back()->with('success', __('Debit note deleted successfully.'));
    }
}
