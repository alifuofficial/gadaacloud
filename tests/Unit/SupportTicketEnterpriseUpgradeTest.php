<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\SupportTicket\Models\Contact;
use Workdo\SupportTicket\Models\Conversion;
use Workdo\SupportTicket\Models\Faq;
use Workdo\SupportTicket\Models\KnowledgeBase;
use Workdo\SupportTicket\Models\KnowledgeBaseCategory;
use Workdo\SupportTicket\Models\QuickLink;
use Workdo\SupportTicket\Models\SupportTicketCustomPage;
use Workdo\SupportTicket\Models\SupportTicketSetting;
use Workdo\SupportTicket\Models\Ticket;
use Workdo\SupportTicket\Models\TicketCategory;
use Workdo\SupportTicket\Models\TicketField;
use Workdo\SupportTicket\Models\TicketFieldValue;
use Workdo\SupportTicket\Traits\SupportTicketControllerHelpers;
use Workdo\SupportTicket\Traits\SupportTicketDateHelpers;

class SupportTicketEnterpriseUpgradeTest extends TestCase
{
    public function test_all_support_ticket_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Contact::class,
            Conversion::class,
            Faq::class,
            KnowledgeBase::class,
            KnowledgeBaseCategory::class,
            QuickLink::class,
            SupportTicketCustomPage::class,
            SupportTicketSetting::class,
            Ticket::class,
            TicketCategory::class,
            TicketField::class,
            TicketFieldValue::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                \App\Traits\TenantScoped::class,
                $traits,
                "{$modelClass} must use App\\Traits\\TenantScoped trait"
            );
        }
    }

    public function test_support_ticket_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/SupportTicket/src/Http/Requests/*.php'));
        $this->assertNotEmpty($requestFiles);

        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_support_ticket_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use SupportTicketDateHelpers;
        };

        $resolved = $helper->resolveSupportTicketDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
    }

    public function test_support_ticket_controller_helpers_bounds_per_page(): void
    {
        $helper = new class {
            use SupportTicketControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));
    }
}
