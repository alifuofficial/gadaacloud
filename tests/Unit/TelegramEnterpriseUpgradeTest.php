<?php

namespace Tests\Unit;

use Tests\TestCase;

class TelegramEnterpriseUpgradeTest extends TestCase
{
    public function test_telegram_service_and_controller_enforce_timeouts_transactions_and_guards(): void
    {
        $serviceContent = file_get_contents(base_path('packages/workdo/Telegram/src/Services/SendMsg.php'));
        $controllerContent = file_get_contents(base_path('packages/workdo/Telegram/src/Http/Controllers/TelegramSettingsController.php'));

        $this->assertStringContainsString('if (!$usr)', $serviceContent);
        $this->assertStringContainsString('if (!$template)', $serviceContent);
        $this->assertStringContainsString('if (!$content)', $serviceContent);
        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $serviceContent);
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $serviceContent);

        $this->assertStringContainsString('DB::transaction', $controllerContent);
        $this->assertStringContainsString('edit-telegram-settings', $controllerContent);
    }
}
