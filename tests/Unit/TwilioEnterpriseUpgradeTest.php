<?php

namespace Tests\Unit;

use Tests\TestCase;

class TwilioEnterpriseUpgradeTest extends TestCase
{
    public function test_twilio_service_and_controller_enforce_timeouts_transactions_and_guards(): void
    {
        $serviceContent = file_get_contents(base_path('packages/workdo/Twilio/src/Services/SendMsg.php'));
        $controllerContent = file_get_contents(base_path('packages/workdo/Twilio/src/Http/Controllers/TwilioSettingsController.php'));

        $this->assertStringContainsString('elseif (Auth::check())', $serviceContent);
        $this->assertStringContainsString('if (!$template)', $serviceContent);
        $this->assertStringContainsString('if (!$content)', $serviceContent);
        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $serviceContent);
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $serviceContent);

        $this->assertStringContainsString('DB::transaction', $controllerContent);
        $this->assertStringContainsString('edit-twilio-settings', $controllerContent);
    }
}
