<?php

namespace Workdo\Slack\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Workdo\Slack\Services\SendMsg;

class SlackSettingsController extends Controller
{
    public function index()
    {
        $slackNotifications = Notification::where('type', 'Slack')->get()->groupBy('module');

        return response()->json([
            'slackNotifications' => $slackNotifications
        ]);
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('edit-slack-settings')) {

            $validator = Validator::make($request->all(), [
                'settings.slack_webhook_url' => 'required|url|max:255',
                'settings.slack_notification_is' => 'required|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->with('error', __('Validation failed'));
            }

            $settings = $request->input('settings', []);
            if (!empty($settings['slack_webhook_url']) && !SendMsg::isValidWebhookUrl($settings['slack_webhook_url'])) {
                return redirect()->back()->with('error', __('The Slack webhook URL is invalid or points to a restricted internal network address.'));
            }

            try {
                DB::transaction(function () use ($settings) {
                    foreach ($settings as $key => $value) {
                        setSetting($key, $value, creatorId());
                    }
                });

                return redirect()->back()->with('success', __('Slack settings save successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to update slack settings: ') . $e->getMessage());
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }
}
