<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\GoogleCaptcha\Rules\RecaptchaRule;

class GoogleCaptchaEnterpriseUpgradeTest extends TestCase
{
    public function test_recaptcha_rule_rejects_empty_response(): void
    {
        $rule = new RecaptchaRule();
        $failedMessage = null;

        $rule->validate('g-recaptcha-response', '', function (string $message) use (&$failedMessage) {
            $failedMessage = $message;
        });

        $this->assertEquals('Please complete the reCAPTCHA verification.', $failedMessage);
    }

    public function test_google_captcha_controller_enforces_permission_and_transaction(): void
    {
        $controllerContent = file_get_contents(
            base_path('packages/workdo/GoogleCaptcha/src/Http/Controllers/GoogleCaptchaSettingsController.php')
        );

        $this->assertStringContainsString('edit-google-captcha-settings', $controllerContent);
        $this->assertStringContainsString("hasRole('superadmin')", $controllerContent);
        $this->assertStringContainsString('DB::transaction', $controllerContent);
    }
}
