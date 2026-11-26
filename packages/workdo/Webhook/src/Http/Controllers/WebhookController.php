<?php

namespace Workdo\Webhook\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Webhook\Models\Webhook;
use Workdo\Webhook\Models\WebhookModule;
use Workdo\Webhook\Listeners\WebhookEventListener;
use Workdo\Webhook\Traits\WebhookControllerHelpers;
use Workdo\Webhook\Traits\WebhookDateHelpers;

class WebhookController extends Controller
{
    use WebhookControllerHelpers, WebhookDateHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-webhooks')) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Permission denied'], 403);
            }
            return back()->with('error', __('Permission denied'));
        }

        $userType = Auth::user()->type;
        
        // Get webhook modules based on user type
        if ($userType === 'superadmin') {
            $webhookModules = WebhookModule::where('type', 'super admin')->get();
        } else {
            $webhookModules = WebhookModule::where('type', 'company')->get();
        }

        // Get user's webhooks with webhook module relationship
        $webhooks = Webhook::with('webhookModule')
            ->where('created_by', creatorId())
            ->get();
            
        // Ensure webhookModule is loaded for each webhook
        $webhooks->load('webhookModule');

        return response()->json([
            'webhooks' => $webhooks->toArray(),
            'webhookModules' => $webhookModules->groupBy('module')->toArray()
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-webhooks')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'method' => 'required|in:GET,POST',
            'action' => 'required|exists:webhook_modules,id',
            'url' => 'required|url'
        ]);

        if (!WebhookEventListener::isValidWebhookUrl($validated['url'])) {
            return back()->with('error', __('The webhook URL is invalid or points to a restricted internal network address.'));
        }

        $webhook = DB::transaction(function () use ($validated) {
            return Webhook::create([
                'method' => $validated['method'],
                'action' => $validated['action'],
                'url' => $validated['url'],
                'creator_id' => Auth::id(),
                'created_by' => creatorId()
            ]);
        });

        return back()->with('success', __('Webhook created successfully'));
    }

    public function update(Request $request, Webhook $webhook)
    {
        if (!Auth::user()->can('edit-webhooks')) {
            return back()->with('error', __('Permission denied'));
        }

        // Check ownership
        if (!$this->assertBelongsToTenant($webhook)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'method' => 'required|in:GET,POST',
            'action' => 'required|exists:webhook_modules,id',
            'url' => 'required|url'
        ]);

        if (!WebhookEventListener::isValidWebhookUrl($validated['url'])) {
            return back()->with('error', __('The webhook URL is invalid or points to a restricted internal network address.'));
        }

        DB::transaction(function () use ($webhook, $validated) {
            $webhook->update($validated);
        });

        return back()->with('success', __('Webhook updated successfully'));
    }

    public function destroy(Webhook $webhook)
    {
        if (!Auth::user()->can('delete-webhooks')) {
            return back()->with('error', __('Permission denied'));
        }

        // Check ownership
        if (!$this->assertBelongsToTenant($webhook)) {
            return back()->with('error', __('Permission denied'));
        }

        DB::transaction(function () use ($webhook) {
            $webhook->delete();
        });

        return back()->with('success', __('Webhook deleted successfully'));
    }
    
    public function toggle(Webhook $webhook)
    {
        if (!Auth::user()->can('edit-webhooks')) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Permission denied'], 403);
            }
            return back()->with('error', __('Permission denied'));
        }

        // Check ownership
        if (!$this->assertBelongsToTenant($webhook)) {
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Permission denied'], 403);
            }
            return back()->with('error', __('Permission denied'));
        }

        $webhook->update(['is_active' => !$webhook->is_active]);

        return back()->with('success', $webhook->is_active ? __('Webhook enabled successfully') : __('Webhook disabled successfully'));
    }

}