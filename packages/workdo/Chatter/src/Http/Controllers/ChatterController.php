<?php

namespace Workdo\Chatter\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Chatter\Models\ChatterMessage;
use Workdo\Chatter\Models\ChatterAttachment;
use Workdo\Chatter\Models\ChatterActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatterController extends Controller
{
    /**
     * Get stream history for a record
     */
    public function getStream($model, $id)
    {
        $companyId = creatorId();

        $messages = ChatterMessage::where('created_by', $companyId)
            ->where('model_type', $model)
            ->where('model_id', $id)
            ->with(['user:id,name,email,avatar', 'attachments'])
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        $activities = ChatterActivity::where('created_by', $companyId)
            ->where('model_type', $model)
            ->where('model_id', $id)
            ->with(['user:id,name,email', 'assignee:id,name,email'])
            ->orderBy('due_date', 'asc')
            ->get();

        // Get list of users for @mention suggestions
        $companyUsers = User::where('created_by', $companyId)
            ->orWhere('id', $companyId)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'messages'     => $messages,
            'activities'   => $activities,
            'companyUsers' => $companyUsers,
        ]);
    }

    /**
     * Post a new chatter note or message
     */
    public function postMessage(Request $request, $model, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'type'    => 'required|string|in:note,message,diff',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $user = Auth::user();
        $companyId = creatorId();

        $chatterMessage = ChatterMessage::create([
            'created_by' => $companyId,
            'model_type' => $model,
            'model_id'   => $id,
            'user_id'    => $user->id,
            'message'    => $request->message,
            'type'       => $request->type,
        ]);

        // Handle File Attachments
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('chatter_uploads', 'public');
                ChatterAttachment::create([
                    'chatter_message_id' => $chatterMessage->id,
                    'file_name'          => $file->getClientOriginalName(),
                    'file_path'          => '/storage/' . $path,
                    'file_type'          => $file->getClientMimeType(),
                    'file_size'          => $file->getSize(),
                ]);
            }
        }

        $chatterMessage->load(['user:id,name,email,avatar', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => $chatterMessage,
        ]);
    }

    /**
     * Schedule a new activity (Call, Email, Meeting, To-Do)
     */
    public function scheduleActivity(Request $request, $model, $id)
    {
        $request->validate([
            'title'         => 'required|string',
            'activity_type' => 'required|string',
            'due_date'      => 'required|date',
            'assigned_to'   => 'nullable|integer',
            'notes'         => 'nullable|string',
        ]);

        $user = Auth::user();
        $companyId = creatorId();

        $activity = ChatterActivity::create([
            'created_by'    => $companyId,
            'model_type'    => $model,
            'model_id'      => $id,
            'user_id'       => $user->id,
            'assigned_to'   => $request->assigned_to ?? $user->id,
            'activity_type' => $request->activity_type,
            'title'         => $request->title,
            'due_date'      => $request->due_date,
            'status'        => 'pending',
            'notes'         => $request->notes,
        ]);

        // Log activity creation in chatter messages
        ChatterMessage::create([
            'created_by' => $companyId,
            'model_type' => $model,
            'model_id'   => $id,
            'user_id'    => $user->id,
            'message'    => "📅 Scheduled activity: **{$activity->title}** (Due: {$activity->due_date})",
            'type'       => 'activity',
        ]);

        $activity->load(['user:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'success'  => true,
            'activity' => $activity,
        ]);
    }

    /**
     * Toggle Activity Completion
     */
    public function toggleActivityStatus($activityId)
    {
        $companyId = creatorId();
        $activity = ChatterActivity::where('created_by', $companyId)->where('id', $activityId)->firstOrFail();
        $activity->status = $activity->status === 'completed' ? 'pending' : 'completed';
        $activity->save();

        return response()->json([
            'success'  => true,
            'activity' => $activity,
        ]);
    }
}
