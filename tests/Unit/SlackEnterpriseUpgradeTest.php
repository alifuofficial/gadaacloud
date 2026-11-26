<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Slack\Services\SendMsg;

class SlackEnterpriseUpgradeTest extends TestCase
{
    public function test_slack_webhook_validator_blocks_ssrf_targets(): void
    {
        $this->assertFalse(SendMsg::isValidWebhookUrl('http://127.0.0.1/webhook'));
        $this->assertFalse(SendMsg::isValidWebhookUrl('http://localhost:8080/hook'));
        $this->assertFalse(SendMsg::isValidWebhookUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(SendMsg::isValidWebhookUrl('http://10.0.0.1/internal'));
        $this->assertFalse(SendMsg::isValidWebhookUrl('file:///etc/passwd'));

        $this->assertTrue(SendMsg::isValidWebhookUrl('https://hooks.slack.com/services/T00000/B00000/XXXXXXXX'));
    }

    public function test_slack_service_and_controller_enforce_timeouts_transactions_and_guards(): void
    {
        $serviceContent = file_get_contents(base_path('packages/workdo/Slack/src/Services/SendMsg.php'));
        $controllerContent = file_get_contents(base_path('packages/workdo/Slack/src/Http/Controllers/SlackSettingsController.php'));

        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $serviceContent);
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $serviceContent);
        $this->assertStringContainsString('self::isValidWebhookUrl($slack_webhook)', $serviceContent);

        $this->assertStringContainsString('DB::transaction', $controllerContent);
        $this->assertStringContainsString('SendMsg::isValidWebhookUrl', $controllerContent);
    }
}
