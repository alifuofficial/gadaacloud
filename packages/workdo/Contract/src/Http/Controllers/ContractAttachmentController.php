<?php

namespace Workdo\Contract\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractAttachment;
use Workdo\Contract\Traits\ContractControllerHelpers;

class ContractAttachmentController extends Controller
{
    use ContractControllerHelpers;

    public function store(Request $request, Contract $contract)
    {
        if (Auth::user()->can('create-contract-attachments') && $this->assertBelongsToTenant($contract)) {
            if (!$request->has('media_paths')) {
                return back()->with('error', __('No media_paths provided'));
            }

            $mediaPaths = $request->input('media_paths');
            if (!is_array($mediaPaths) || empty($mediaPaths)) {
                return back()->with('error', __('Invalid media paths'));
            }

            DB::transaction(function () use ($mediaPaths, $contract) {
                foreach ($mediaPaths as $mediaPath) {
                    if (!empty($mediaPath)) {
                        ContractAttachment::create([
                            'contract_id' => $contract->id,
                            'file_name' => basename($mediaPath),
                            'file_path' => basename($mediaPath),
                            'uploaded_by' => Auth::id(),
                            'creator_id' => Auth::id(),
                            'created_by' => creatorId(),
                        ]);
                    }
                }
            });

            return back()->with('success', __('The attachment has been uploaded successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractAttachment $attachment)
    {
        if (Auth::user()->can('delete-contract-attachments') && $this->assertBelongsToTenant($attachment)) {
            $filePath = $attachment->file_path;
            DB::transaction(function () use ($attachment) {
                $attachment->delete();
            });

            // Delete the physical file if it exists
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            return back()->with('success', __('The attachment has been deleted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
