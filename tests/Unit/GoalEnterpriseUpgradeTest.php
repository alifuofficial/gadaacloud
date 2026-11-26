<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Services\JournalService;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalCategory;
use Workdo\Goal\Models\GoalContribution;
use Workdo\Goal\Models\GoalMilestone;
use Workdo\Goal\Models\GoalTracking;
use Workdo\Goal\Services\GoalService;
use Workdo\Goal\Traits\GoalControllerHelpers;
use Workdo\Goal\Traits\GoalDateHelpers;

class GoalEnterpriseUpgradeTest extends TestCase
{
    public function test_all_goal_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Goal::class,
            GoalCategory::class,
            GoalContribution::class,
            GoalMilestone::class,
            GoalTracking::class,
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

    public function test_journal_service_dispatches_account_module_event(): void
    {
        $reflection = new \ReflectionClass(JournalService::class);
        $method = $reflection->getMethod('dispatchBudgetUpdate');
        $method->setAccessible(true);

        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('use Workdo\Account\Events\UpdateBudgetSpending;', $source);
        $this->assertStringNotContainsString('use App\Events\UpdateBudgetSpending;', $source);
    }

    public function test_goal_milestone_casts_achieved_amount_correctly(): void
    {
        $milestone = new GoalMilestone();
        $casts = $milestone->getCasts();

        $this->assertArrayHasKey('achieved_amount', $casts);
        $this->assertSame('decimal:2', $casts['achieved_amount']);
        $this->assertArrayNotHasKey('current_amount', $casts);
    }

    public function test_double_entry_savings_goal_supports_debit_asset_accounts(): void
    {
        $service = new GoalService();

        // 1. Savings Goal linked to Debit-Normal Bank Account (Asset)
        $assetAccount = new ChartOfAccount();
        $assetAccount->id = 101;
        $assetAccount->normal_balance = 'debit';

        $savingsGoal = new Goal();
        $savingsGoal->account_id = 101;
        $savingsGoal->goal_type = 'savings';
        $savingsGoal->setRelation('account', $assetAccount);

        // Journal entry with 5000 debit (cash deposit)
        $item1 = (object) [
            'account_id'    => 101,
            'debit_amount'  => 5000.0,
            'credit_amount' => 0.0,
        ];
        $journalEntry = (object) [
            'items' => collect([$item1]),
        ];

        $contribution = $service->calculateContributionFromJournalEntry($savingsGoal, $journalEntry);
        $this->assertEquals(5000.0, $contribution, 'Debit to cash/bank asset account must count as savings contribution');
    }

    public function test_double_entry_revenue_and_debt_goals(): void
    {
        $service = new GoalService();

        // 2. Debt Reduction Goal linked to Credit-Normal Liability
        $liabilityAccount = new ChartOfAccount();
        $liabilityAccount->id = 201;
        $liabilityAccount->normal_balance = 'credit';

        $debtGoal = new Goal();
        $debtGoal->account_id = 201;
        $debtGoal->goal_type = 'debt_reduction';
        $debtGoal->setRelation('account', $liabilityAccount);

        // Journal entry paying down 3000 of debt (debit liability)
        $itemDebit = (object) [
            'account_id'    => 201,
            'debit_amount'  => 3000.0,
            'credit_amount' => 0.0,
        ];
        $journalEntry = (object) [
            'items' => collect([$itemDebit]),
        ];

        $debtContribution = $service->calculateContributionFromJournalEntry($debtGoal, $journalEntry);
        $this->assertEquals(3000.0, $debtContribution, 'Debit to liability account reduces debt and counts toward goal');

        // 3. Revenue Target Goal linked to Credit-Normal Revenue
        $revenueAccount = new ChartOfAccount();
        $revenueAccount->id = 301;
        $revenueAccount->normal_balance = 'credit';

        $revenueGoal = new Goal();
        $revenueGoal->account_id = 301;
        $revenueGoal->goal_type = 'revenue_target';
        $revenueGoal->setRelation('account', $revenueAccount);

        // Journal entry with 8500 credit (earned revenue)
        $itemCredit = (object) [
            'account_id'    => 301,
            'debit_amount'  => 0.0,
            'credit_amount' => 8500.0,
        ];
        $journalEntryRev = (object) [
            'items' => collect([$itemCredit]),
        ];

        $revContribution = $service->calculateContributionFromJournalEntry($revenueGoal, $journalEntryRev);
        $this->assertEquals(8500.0, $revContribution, 'Credit to revenue account counts toward revenue target goal');
    }

    public function test_safe_per_page_helper_clamps_bounds(): void
    {
        $helper = new class {
            use GoalControllerHelpers {
                safePerPage as public;
            }
        };

        $this->assertSame(10, $helper->safePerPage(10, 100));
    }

    public function test_goal_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use GoalDateHelpers;
        };

        $resolved = $helper->resolveGoalDateRange('2017');

        $this->assertArrayHasKey('start_date', $resolved);
        $this->assertArrayHasKey('end_date', $resolved);
        $this->assertStringStartsWith('2024-', $resolved['start_date']);
        $this->assertStringStartsWith('2025-', $resolved['end_date']);
        $this->assertStringContainsString('/', $helper->formatDualGoalDate('2025-01-15'));
    }
}
