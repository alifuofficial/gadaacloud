<?php

namespace Workdo\Contract\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractNote;
use Workdo\Contract\Traits\ContractControllerHelpers;

class ContractNoteController extends Controller
{
    use ContractControllerHelpers;

    public function store(Request $request, Contract $contract)
    {
        if (Auth::user()->can('create-contract-notes') && $this->assertBelongsToTenant($contract)) {
            $request->validate([
                'note' => 'required|string|max:20000',
            ]);

            $cleanNote = \App\Support\HtmlSanitizer::sanitize($request->note);
            if ($cleanNote === null) {
                return back()->withErrors(['note' => __('The note field is required.')]);
            }

            DB::transaction(function () use ($contract, $cleanNote) {
                ContractNote::create([
                    'contract_id' => $contract->id,
                    'note' => $cleanNote,
                    'user_id' => Auth::id(),
                    'creator_id' => Auth::id(),
                    'created_by' => creatorId(),
                ]);
            });

            return back()->with('success', __('The note has been created successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(Request $request, ContractNote $note)
    {
        if (Auth::user()->can('edit-contract-notes') && $this->assertBelongsToTenant($note)) {
            $request->validate([
                'note' => 'required|string|max:20000',
            ]);

            $cleanNote = \App\Support\HtmlSanitizer::sanitize($request->note);
            if ($cleanNote === null) {
                return back()->withErrors(['note' => __('The note field is required.')]);
            }

            DB::transaction(function () use ($note, $cleanNote) {
                $note->update([
                    'note' => $cleanNote,
                    'is_edited' => true,
                ]);
            });

            return back()->with('success', __('The note details are updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractNote $note)
    {
        if (Auth::user()->can('delete-contract-notes') && $this->assertBelongsToTenant($note)) {
            DB::transaction(function () use ($note) {
                $note->delete();
            });
            return back()->with('success', __('The note has been deleted.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
