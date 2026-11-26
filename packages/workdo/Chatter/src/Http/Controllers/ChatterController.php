<?php

namespace Workdo\Chatter\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Chatter\Models\ChatterMessage;
use Workdo\Chatter\Models\ChatterAttachment;
use Workdo\Chatter\Models\ChatterActivity;
use Workdo\Chatter\Models\ChatterFollower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Workdo\Chatter\Traits\ChatterControllerHelpers;
use Workdo\Chatter\Traits\ChatterDateHelpers;

class ChatterController extends Controller
{
    use ChatterControllerHelpers, ChatterDateHelpers;
    /**
     * Normalize model string to clean basename
     */
    private function cleanModelType(string $model): string
    {
        $parts = explode('\\', str_replace('/', '\\', $model));
        $base  = end($parts);
        return Str::studly($base);
    }

    /**
     * Get stream history for a record (messages, activities, followers, user suggestions)
     */
    public function getStream($model, $id)
    {
        $companyId = creatorId();
        $modelType = $this->cleanModelType($model);

        $messages = ChatterMessage::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->with(['user:id,name,email,avatar', 'attachments'])
            ->orderBy('id', 'desc')
            ->take(60)
            ->get();

        $activities = ChatterActivity::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->with(['user:id,name,email,avatar', 'assignee:id,name,email,avatar'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderBy('due_date', 'asc')
            ->get();

        $followers = ChatterFollower::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->with('user:id,name,email,avatar')
            ->get();

        $isFollowing = ChatterFollower::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->where('user_id', Auth::id())
            ->exists();

        // Users within the same tenant for @mentions and activity assignment
        $companyUsers = User::where(function ($q) use ($companyId) {
            $q->where('created_by', $companyId)
              ->orWhere('id', $companyId);
        })->get(['id', 'name', 'email', 'avatar']);

        return response()->json([
            'success'      => true,
            'model_type'   => $modelType,
            'model_id'     => (int) $id,
            'messages'     => $messages,
            'activities'   => $activities,
            'followers'    => $followers,
            'is_following' => $isFollowing,
            'company_users'=> $companyUsers,
        ]);
    }

    /**
     * Post a new chatter note or message with attachments
     */
    public function postMessage(Request $request, $model, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'type'    => 'required|string|in:note,message,diff',
            'files.*' => 'nullable|file|max:15360|mimes:pdf,png,jpg,jpeg,webp,xlsx,xls,csv,doc,docx,txt,zip',
        ]);

        $user      = Auth::user();
        $companyId = creatorId();
        $modelType = $this->cleanModelType($model);

        $chatterMessage = DB::transaction(function () use ($request, $user, $companyId, $modelType, $id) {
            $chatterMessage = ChatterMessage::create([
                'created_by' => $companyId,
                'model_type' => $modelType,
                'model_id'   => $id,
                'user_id'    => $user->id,
                'message'    => $request->message,
                'type'       => $request->type,
            ]);

            // Secure file upload handler
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('chatter_uploads/' . $companyId, 'public');
                    ChatterAttachment::create([
                        'created_by'         => $companyId,
                        'chatter_message_id' => $chatterMessage->id,
                        'file_name'          => $file->getClientOriginalName(),
                        'file_path'          => Storage::url($path),
                        'file_type'          => $file->getClientMimeType(),
                        'file_size'          => $file->getSize(),
                    ]);
                }
            }

            // Auto-add message author as follower
            ChatterFollower::firstOrCreate([
                'created_by' => $companyId,
                'model_type' => $modelType,
                'model_id'   => $id,
                'user_id'    => $user->id,
            ]);

            return $chatterMessage;
        });

        $chatterMessage->load(['user:id,name,email,avatar', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => $chatterMessage,
        ]);
    }

    /**
     * Schedule a new activity (Call, Email, Meeting, To-Do, Review)
     */
    public function scheduleActivity(Request $request, $model, $id)
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'activity_type' => 'required|string|in:call,email,meeting,todo,review',
            'due_date'      => 'required|date',
            'assigned_to'   => 'nullable|integer',
            'notes'         => 'nullable|string',
        ]);

        $user      = Auth::user();
        $companyId = creatorId();
        $modelType = $this->cleanModelType($model);

        // Validate assigned user belongs to this tenant
        $assigneeId = $request->assigned_to ?? $user->id;
        $isValidAssignee = User::where('id', $assigneeId)
            ->where(function ($q) use ($companyId) {
                $q->where('created_by', $companyId)->orWhere('id', $companyId);
            })->exists();

        if (!$isValidAssignee) {
            $assigneeId = $user->id;
        }

        $activity = ChatterActivity::create([
            'created_by'    => $companyId,
            'model_type'    => $modelType,
            'model_id'      => $id,
            'user_id'       => $user->id,
            'assigned_to'   => $assigneeId,
            'activity_type' => $request->activity_type,
            'title'         => $request->title,
            'due_date'      => $request->due_date,
            'status'        => 'pending',
            'notes'         => $request->notes,
        ]);

        $assigneeUser = User::find($assigneeId);
        $assigneeName = $assigneeUser ? $assigneeUser->name : 'Staff';
        $typeIcons = ['call' => '📞', 'email' => '✉️', 'meeting' => '📅', 'todo' => '📋', 'review' => '🔍'];
        $icon = $typeIcons[$request->activity_type] ?? '📋';

        // Log activity creation in chatter messages stream
        ChatterMessage::create([
            'created_by' => $companyId,
            'model_type' => $modelType,
            'model_id'   => $id,
            'user_id'    => $user->id,
            'message'    => "{$icon} Scheduled **" . ucfirst($request->activity_type) . "**: **{$activity->title}** for @{$assigneeName} (Due: {$activity->due_date})",
            'type'       => 'activity',
        ]);

        // Auto-follow assignee
        ChatterFollower::firstOrCreate([
            'created_by' => $companyId,
            'model_type' => $modelType,
            'model_id'   => $id,
            'user_id'    => $assigneeId,
        ]);

        $activity->load(['user:id,name,email,avatar', 'assignee:id,name,email,avatar']);

        return response()->json([
            'success'  => true,
            'activity' => $activity,
        ]);
    }

    /**
     * Mark an Activity as Completed with optional feedback note
     */
    public function toggleActivityStatus(Request $request, $activityId)
    {
        $companyId = creatorId();
        $user      = Auth::user();

        $activity = ChatterActivity::where('created_by', $companyId)
            ->where('id', $activityId)
            ->firstOrFail();

        $newStatus = $activity->status === 'completed' ? 'pending' : 'completed';
        $feedback  = $request->feedback;

        $activity->status       = $newStatus;
        $activity->feedback     = $feedback;
        $activity->completed_at = $newStatus === 'completed' ? now() : null;
        $activity->save();

        if ($newStatus === 'completed') {
            $feedbackStr = $feedback ? "\n*Feedback:* {$feedback}" : '';
            ChatterMessage::create([
                'created_by' => $companyId,
                'model_type' => $activity->model_type,
                'model_id'   => $activity->model_id,
                'user_id'    => $user->id,
                'message'    => "✅ Completed activity: **{$activity->title}** by {$user->name}{$feedbackStr}",
                'type'       => 'activity',
            ]);
        }

        $activity->load(['user:id,name,email,avatar', 'assignee:id,name,email,avatar']);

        return response()->json([
            'success'  => true,
            'activity' => $activity,
        ]);
    }

    /**
     * Follow or unfollow a record
     */
    public function toggleFollow(Request $request, $model, $id)
    {
        $companyId = creatorId();
        $user      = Auth::user();
        $modelType = $this->cleanModelType($model);

        $follower = ChatterFollower::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($follower) {
            $follower->delete();
            $isFollowing = false;
        } else {
            ChatterFollower::create([
                'created_by' => $companyId,
                'model_type' => $modelType,
                'model_id'   => $id,
                'user_id'    => $user->id,
            ]);
            $isFollowing = true;
        }

        $followers = ChatterFollower::where('created_by', $companyId)
            ->where('model_type', $modelType)
            ->where('model_id', $id)
            ->with('user:id,name,email,avatar')
            ->get();

        return response()->json([
            'success'      => true,
            'is_following' => $isFollowing,
            'followers'    => $followers,
        ]);
    }
}
