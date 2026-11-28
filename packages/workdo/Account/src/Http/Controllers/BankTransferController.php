<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\CreateBankTransfer;
use Workdo\Account\Events\DestroyBankTransfer;
use Workdo\Account\Events\ProcessBankTransfer;
use Workdo\Account\Events\UpdateBankTransfer;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreBankTransferRequest;
use Workdo\Account\Http\Requests\UpdateBankTransferRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Services\BankTransactionsService;
use Workdo\Account\Services\JournalService;
use Workdo\Account\Traits\AccountControllerHelpers;
use Workdo\Account\Traits\AccountDateHelpers;

class BankTransferController extends Controller
{
    use AccountControllerHelpers, AccountDateHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-bank-transfers')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            request('sort'),
            ['transfer_number', 'transfer_date', 'transfer_amount', 'transfer_charges', 'status', 'created_at'],
            'created_at',
            request('direction', 'desc')
        );

        $banktransfers = BankTransfer::query()
            ->with(['fromAccount', 'toAccount'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-bank-transfers')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-bank-transfers')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('transfer_number'), function ($q) {
                $q->where(function ($query) {
                    $query->where('transfer_number', 'like', '%' . request('transfer_number') . '%')
                        ->orWhere('reference_number', 'like', '%' . request('transfer_number') . '%');
                });
            })
            ->when(request('status') !== null && request('status') !== '', fn ($q) => $q->where('status', request('status')))
            ->when(request('from_account_id'), fn ($q) => $q->where('from_account_id', request('from_account_id')))
            ->when(request('to_account_id'), fn ($q) => $q->where('to_account_id', request('to_account_id')))
            ->when(request('sort'), fn ($q) => $q->orderBy($sortCol, $sortDir), fn ($q) => $q->latest())
            ->paginate($this->safePerPage(10, 100))
            ->withQueryString();

        $bankaccounts = BankAccount::where('is_active', true)
            ->where('created_by', creatorId())
            ->select('id', 'account_name', 'current_balance', 'currency_code')
            ->get();

        return Inertia::render('Account/BankTransfers/Index', [
            'banktransfers' => $banktransfers,
            'bankaccounts' => $bankaccounts,
        ]);
    }

    public function store(StoreBankTransferRequest $request)
    {
        if (!Auth::user()->can('create-bank-transfers')) {
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if ((int) $validated['from_account_id'] === (int) $validated['to_account_id']) {
            return back()->with('error', __('Source and destination accounts must be different.'));
        }

        $fromAccount = BankAccount::where('id', $validated['from_account_id'])
            ->where('created_by', $companyId)
            ->first();
        $toAccount = BankAccount::where('id', $validated['to_account_id'])
            ->where('created_by', $companyId)
            ->first();

        if (!$fromAccount || !$toAccount) {
            return back()->with('error', __('Invalid bank account selected.'));
        }

        $totalAmount = (float) $validated['transfer_amount'] + (float) ($validated['transfer_charges'] ?? 0);
        if ((float) $fromAccount->current_balance < $totalAmount) {
            return back()->with('error', __('Insufficient balance in source account'));
        }

        $banktransfer = new BankTransfer();
        $banktransfer->transfer_number = BankTransfer::generateTransferNumber();
        $banktransfer->transfer_date = $validated['transfer_date'];
        $banktransfer->from_account_id = $fromAccount->id;
        $banktransfer->to_account_id = $toAccount->id;
        $banktransfer->transfer_amount = $validated['transfer_amount'];
        $banktransfer->transfer_charges = $validated['transfer_charges'] ?? 0;
        $banktransfer->reference_number = $validated['reference_number'] ?? null;
        $banktransfer->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null) ?? '';
        $banktransfer->status = 'pending';
        $banktransfer->creator_id = Auth::id();
        $banktransfer->created_by = $companyId;
        $banktransfer->save();

        CreateBankTransfer::dispatch($request, $banktransfer);

        return redirect()->route('account.bank-transfers.index')->with('success', __('The bank transfer has been created successfully.'));
    }

    public function update(UpdateBankTransferRequest $request, BankTransfer $banktransfer)
    {
        if (!Auth::user()->can('edit-bank-transfers') || $banktransfer->created_by != creatorId()) {
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }

        if ($banktransfer->status !== 'pending') {
            return back()->with('error', __('Only pending transfers can be edited'));
        }

        $validated = $request->validated();
        $companyId = creatorId();

        if ((int) $validated['from_account_id'] === (int) $validated['to_account_id']) {
            return back()->with('error', __('Source and destination accounts must be different.'));
        }

        $fromAccount = BankAccount::where('id', $validated['from_account_id'])
            ->where('created_by', $companyId)
            ->first();
        $toAccount = BankAccount::where('id', $validated['to_account_id'])
            ->where('created_by', $companyId)
            ->first();

        if (!$fromAccount || !$toAccount) {
            return back()->with('error', __('Invalid bank account selected.'));
        }

        $totalAmount = (float) $validated['transfer_amount'] + (float) ($validated['transfer_charges'] ?? 0);
        if ((float) $fromAccount->current_balance < $totalAmount) {
            return back()->with('error', __('Insufficient balance in source account'));
        }

        $banktransfer->transfer_date = $validated['transfer_date'];
        $banktransfer->from_account_id = $fromAccount->id;
        $banktransfer->to_account_id = $toAccount->id;
        $banktransfer->transfer_amount = $validated['transfer_amount'];
        $banktransfer->transfer_charges = $validated['transfer_charges'] ?? 0;
        $banktransfer->reference_number = $validated['reference_number'] ?? null;
        $banktransfer->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null) ?? '';
        $banktransfer->save();

        UpdateBankTransfer::dispatch($request, $banktransfer);

        return redirect()->back()->with('success', __('The bank transfer details are updated successfully.'));
    }

    public function destroy(BankTransfer $banktransfer)
    {
        if (!Auth::user()->can('delete-bank-transfers') || $banktransfer->created_by != creatorId()) {
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }

        if ($banktransfer->status !== 'pending') {
            return back()->with('error', __('Only pending transfers can be deleted'));
        }

        DestroyBankTransfer::dispatch($banktransfer);
        $banktransfer->delete();

        return redirect()->back()->with('success', __('The bank transfer has been deleted.'));
    }

    public function process(BankTransfer $banktransfer)
    {
        if (!Auth::user()->can('process-bank-transfers') || $banktransfer->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        if ($banktransfer->status !== 'pending') {
            return back()->with('error', __('Transfer is not in pending status'));
        }

        try {
            DB::transaction(function () use ($banktransfer) {
                $lockedTransfer = BankTransfer::where('id', $banktransfer->id)
                    ->where('created_by', creatorId())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedTransfer->status !== 'pending') {
                    throw new \Exception(__('Transfer is not in pending status'));
                }

                $bankTransactionsService = new BankTransactionsService();
                $bankTransactionsService->createTransferBankTransactions($lockedTransfer);

                $journalService = new JournalService();
                $journalEntry = $journalService->createBankTransferJournal($lockedTransfer);

                $lockedTransfer->status = 'completed';
                $lockedTransfer->journal_entry_id = $journalEntry?->id;
                $lockedTransfer->save();
            });

            $banktransfer->refresh();
            ProcessBankTransfer::dispatch($banktransfer);

            return back()->with('success', __('Bank transfer processed successfully'));
        } catch (\Exception $e) {
            BankTransfer::where('id', $banktransfer->id)
                ->where('created_by', creatorId())
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            return back()->with('error', __('Error processing transfer: ') . $e->getMessage());
        }
    }
}
