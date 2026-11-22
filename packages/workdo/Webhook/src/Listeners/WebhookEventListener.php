<?php

namespace Workdo\Webhook\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Workdo\Webhook\Models\Webhook;
use Workdo\Webhook\Models\WebhookModule;

class WebhookEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 2;
    public $timeout = 15;
    public function handle($event)
    {
        $companyId = $this->getContextUserId($event);
        if (Module_is_active('Webhook', $companyId)) {
        $eventClass = get_class($event);
        $events = config('webhook-events.events', []);

        if (!isset($events[$eventClass])) return;

        $config = $events[$eventClass];

        // Determine current user type to get appropriate webhook module
        $currentUserType = \Auth::check() ? \Auth::user()->type : null;

        // For CreateUser event, determine which webhook module to use based on current user
        if ($eventClass === 'App\\Events\\CreateUser') {
            if ($currentUserType === 'superadmin') {
                // Use superadmin webhook module
                $webhookModule = WebhookModule::where('module', 'General')
                    ->where('submodule', 'New User')
                    ->where('type', 'super admin')
                    ->first();
            } else {
                // Use company webhook module
                $webhookModule = WebhookModule::where('module', 'General')
                    ->where('submodule', 'New User')
                    ->where('type', 'company')
                    ->first();
            }
        } else {
            // For other events, use config as normal
            $webhookModule = WebhookModule::where('module', $config['module'])
                ->where('submodule', $config['action'])
                ->where('type', $config['type'])
                ->first();
        }

        if (!$webhookModule) return;

        // Filter webhooks based on user type and context
        $webhooks = Webhook::where('action', $webhookModule->id)->where('is_active', true);

        // For superadmin webhooks, only get superadmin webhooks
        if ($webhookModule->type === 'super admin') {
            $webhooks = $webhooks->whereHas('createdBy', function($q) {
                $q->where('type', 'superadmin');
            });
        } else {
            // For company webhooks, get company context
            if (!$companyId) return;

            $webhooks = $webhooks->where('created_by', $companyId);
        }

        $webhooks = $webhooks->get();

        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $event, $config);
        }
        }
    }

    private function sendWebhook($webhook, $event, $config)
    {
        if (!self::isValidWebhookUrl($webhook->url)) {
            \Log::warning("SSRF Defense: Blocked webhook request to invalid or internal destination URL: {$webhook->url}");
            return;
        }

        $data = $this->extractEventData($event, $config);

        $payload = [
            'event' => $webhook->webhookModule->submodule ?? 'Unknown Event',
            'module' => $webhook->webhookModule->module ?? 'Unknown Module',
            'timestamp' => now()->toISOString(),
            'data' => $data
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhook->url,
            CURLOPT_CUSTOMREQUEST => $webhook->method,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Workdo-Webhook/1.0'
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function extractEventData($event, $config)
    {
        if (isset($config['extractor']) && class_exists($config['extractor'])) {
            return $config['extractor']::extract($event);
        }
        return [];
    }

    private function getContextUserId($event)
    {
        // Check if event has context user ID (for unauthenticated contexts)
        if (property_exists($event, 'contextUserId') && $event->contextUserId) {
            return $event->contextUserId;
        }

        // Fallback to authenticated user
        return Auth::check() ? creatorId() : null;
    }

    /**
     * Validate webhook URL against SSRF (loopback, private networks, cloud metadata).
     */
    public static function isValidWebhookUrl($url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        $host = strtolower($parsed['host'] ?? '');
        if (empty($host)) {
            return false;
        }

        if (in_array($host, ['localhost', 'ip6-localhost', 'ip6-loopback'])) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (
            str_starts_with($ip, '127.') ||
            str_starts_with($ip, '169.254.') ||
            str_starts_with($ip, '0.') ||
            $ip === '::1'
        ) {
            return false;
        }

        return true;
    }
}