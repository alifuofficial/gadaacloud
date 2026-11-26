<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\AIAssistant\Http\Controllers\AIAssistantSettingsController;
use Workdo\AIAssistant\Http\Controllers\AIGeneratorController;
use Workdo\AIAssistant\Traits\AIAssistantControllerHelpers;
use Workdo\AIAssistant\Traits\AIAssistantDateHelpers;
use Workdo\LandingPage\Http\Controllers\CustomPageController;
use Workdo\LandingPage\Http\Controllers\NewsletterSubscriberController;
use Workdo\LandingPage\Models\CustomPage;
use Workdo\LandingPage\Traits\LandingPageControllerHelpers;
use Workdo\LandingPage\Traits\LandingPageDateHelpers;

class LandingPageAndAIUpgradeTest extends TestCase
{
    public function test_landing_page_and_ai_controllers_wire_enterprise_traits(): void
    {
        foreach ([CustomPageController::class, NewsletterSubscriberController::class] as $controller) {
            $traits = class_uses_recursive($controller);
            $this->assertArrayHasKey(LandingPageControllerHelpers::class, $traits);
            $this->assertArrayHasKey(LandingPageDateHelpers::class, $traits);
        }

        foreach ([AIAssistantSettingsController::class, AIGeneratorController::class] as $controller) {
            $traits = class_uses_recursive($controller);
            $this->assertArrayHasKey(AIAssistantControllerHelpers::class, $traits);
            $this->assertArrayHasKey(AIAssistantDateHelpers::class, $traits);
        }
    }

    public function test_landing_page_controllers_have_no_raw_sort_or_unbounded_paginate(): void
    {
        $files = [
            base_path('packages/workdo/LandingPage/src/Http/Controllers/CustomPageController.php'),
            base_path('packages/workdo/LandingPage/src/Http/Controllers/NewsletterSubscriberController.php'),
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/->orderBy\(\s*\$request->sort/', $content);
            $this->assertDoesNotMatchRegularExpression('/->paginate\(\s*\$request->get\(/', $content);
        }
    }

    public function test_landing_page_and_ai_date_helpers_resolve_ethiopian_fiscal_year(): void
    {
        $lpDate = new class {
            use LandingPageDateHelpers;
        };
        $aiDate = new class {
            use AIAssistantDateHelpers;
        };

        $lpRange = $lpDate->resolveLandingPageDateRange('2017');
        $aiRange = $aiDate->resolveAIAssistantDateRange('2017');

        $this->assertStringStartsWith('2024-', $lpRange['start_date']);
        $this->assertStringStartsWith('2025-', $lpRange['end_date']);
        $this->assertStringStartsWith('2024-', $aiRange['start_date']);
        $this->assertStringStartsWith('2025-', $aiRange['end_date']);
    }

    public function test_landing_page_and_ai_controller_helpers_bound_per_page_and_safe_sort(): void
    {
        $lpHelper = new class {
            use LandingPageControllerHelpers;
        };
        $aiHelper = new class {
            use AIAssistantControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $lpHelper->safePerPage(10, 100));
        $this->assertEquals(100, $aiHelper->safePerPage(10, 100));

        request()->merge(['per_page' => -1]);
        $this->assertEquals(10, $lpHelper->safePerPage(10, 100));
        $this->assertEquals(10, $aiHelper->safePerPage(10, 100));

        $query = CustomPage::query();
        $lpHelper->applySafeSort($query, ['id', 'title', 'slug'], 'injected_col; DROP TABLE custom_pages;', 'INVALID');
        $orders = $query->getQuery()->orders;
        $this->assertEquals('created_at', $orders[0]['column']);
        $this->assertEquals('desc', $orders[0]['direction']);
    }
}
