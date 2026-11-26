<?php

namespace Workdo\Contract\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractComment;
use Workdo\Contract\Traits\ContractControllerHelpers;

class ContractCommentController extends Controller
{
    use ContractControllerHelpers;

    public function store(Request $request, Contract $contract)
    {
        if (Auth::user()->can('create-contract-comments') && $this->assertBelongsToTenant($contract)) {
            $request->validate([
                'comment' => 'required|string|max:1000',
            ]);

            DB::transaction(function () use ($contract, $request) {
                ContractComment::create([
                    'contract_id' => $contract->id,
                    'comment' => $request->comment,
                    'user_id' => Auth::id(),
                    'creator_id' => Auth::id(),
                    'created_by' => creatorId(),
                ]);
            });

            return back()->with('success', __('The comment has been created successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(Request $request, ContractComment $comment)
    {
        if (Auth::user()->can('edit-contract-comments') && $this->assertBelongsToTenant($comment)) {
            $request->validate([
                'comment' => 'required|string|max:1000',
            ]);

            DB::transaction(function () use ($comment, $request) {
                $comment->update([
                    'comment' => $request->comment,
                    'is_edited' => true,
                ]);
            });

            return back()->with('success', __('The comment details are updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractComment $comment)
    {
        if (Auth::user()->can('delete-contract-comments') && $this->assertBelongsToTenant($comment)) {
            DB::transaction(function () use ($comment) {
                $comment->delete();
            });
            return back()->with('success', __('The comment has been deleted.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}