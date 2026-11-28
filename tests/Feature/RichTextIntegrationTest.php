<?php

namespace Tests\Feature;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Coupon;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\HtmlSanitizer;
use App\Traits\ControllerHelpers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Once;
use Tests\TestCase;
use Workdo\Account\Http\Requests\StoreBankTransferRequest;
use Workdo\Account\Http\Requests\StoreChartOfAccountRequest;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractComment;
use Workdo\Hrm\Http\Requests\StoreEmployeeDocumentTypeRequest;
use Workdo\Hrm\Http\Requests\StoreEmployeeTransferRequest;
use Workdo\Hrm\Http\Requests\StorePayrollRequest;

class RichTextIntegrationTest extends TestCase
{
    protected User $company1;
    protected User $company2;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('type')->default('company');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('name');
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->decimal('yearly_price', 8, 2)->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_enable')->default(true);
            $table->boolean('for_admin')->default(false);
            $table->string('package_name')->default('Core');
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->decimal('discount', 10, 2);
            $table->integer('limit')->nullable();
            $table->enum('type', ['percentage', 'flat', 'fixed']);
            $table->decimal('minimum_spend', 10, 2)->nullable();
            $table->decimal('maximum_spend', 10, 2)->nullable();
            $table->integer('limit_per_user')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->json('included_module')->nullable();
            $table->json('excluded_module')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->decimal('value', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default('0');
            $table->string('source_type')->default('contract');
            $table->string('template_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->text('comment');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_edited')->default(false);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Gate::before(fn () => true);

        $this->company1 = User::forceCreate([
            'id' => 1501,
            'name' => 'Gadaa RichText Tenant One',
            'email' => 'richtext1@gadaacloud.test',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1501,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 1502,
            'name' => 'Gadaa RichText Tenant Two',
            'email' => 'richtext2@gadaacloud.test',
            'password' => bcrypt('password'),
            'type' => 'company',
            'created_by' => 1502,
        ]);

        DB::table('settings')->insert([
            ['key' => 'dateFormat', 'value' => 'Y-m-d', 'created_by' => 1501],
            ['key' => 'timeFormat', 'value' => 'H:i', 'created_by' => 1501],
        ]);
    }

    public function test_controller_helpers_sanitize_and_extract_plain_text(): void
    {
        $helper = new class {
            use ControllerHelpers;

            public function clean(?string $html): ?string
            {
                return $this->sanitizeRichText($html);
            }

            public function plain(?string $html, ?int $max = null): string
            {
                return $this->richTextToPlainText($html, $max);
            }
        };

        $dirty = '<h2>Project Notes</h2>'
            . '<p onclick="steal()">Summary &amp; <mark>Highlight</mark> H<sub>2</sub>O 1<sup>st</sup></p>'
            . '<ul data-type="taskList"><li data-checked="true"><label><input type="checkbox" checked></label><div><p>Reviewed</p></div></li></ul>'
            . '<table><thead><tr><th colspan="2">Cost</th></tr></thead><tbody><tr><td>Item A</td><td>500</td></tr></tbody></table>'
            . '<p><a href="https://gadaacloud.com">Docs</a><a href="javascript:alert(1)">Bad Link</a></p>'
            . '<img src="/storage/diagram.png" onerror="alert(9)" alt="Diagram">'
            . '<script>alert("xss")</script><iframe src="https://evil.invalid"></iframe>';

        $clean = $helper->clean($dirty);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('<h2>Project Notes</h2>', $clean);
        $this->assertStringContainsString('<mark>Highlight</mark>', $clean);
        $this->assertStringContainsString('<sub>2</sub>', $clean);
        $this->assertStringContainsString('<sup>st</sup>', $clean);
        $this->assertStringContainsString('data-type="taskList"', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('colspan="2"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('<img src="/storage/diagram.png" alt="Diagram">', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);

        $this->assertNull($helper->clean('<p></p>'));
        $this->assertNull($helper->clean('<p>&nbsp;</p>'));
        $this->assertSame('Plain company & Sons', $helper->clean('Plain company & Sons'));

        $plain = $helper->plain($dirty, 60);
        $this->assertStringNotContainsString('<', $plain);
        $this->assertStringContainsString('Summary & Highlight', $plain);
    }

    public function test_raised_form_request_limits_allow_long_tiptap_html_payloads(): void
    {
        Auth::login($this->company1);
        Once::flush();

        // ~3,200 chars exceeds legacy 500 / 1000 / 2000 char caps while staying well below 10k/20k limits
        $longRichHtml = str_repeat('<p><strong>Clause:</strong> Verified ledger &amp; audit trail.</p>', 45);
        $over10kHtml = '<p>' . str_repeat('A', 10050) . '</p>';
        $over20kHtml = '<p>' . str_repeat('B', 20050) . '</p>';

        $transferRules = (new StoreTransferRequest())->rules()['notes'];
        $this->assertTrue(Validator::make(['notes' => $longRichHtml], ['notes' => $transferRules])->passes());
        $this->assertTrue(Validator::make(['notes' => $over10kHtml], ['notes' => $transferRules])->fails());

        $requestClasses = [
            'bank_transfer' => [(new StoreBankTransferRequest())->rules()['description'], 'description'],
            'chart_of_account' => [(new StoreChartOfAccountRequest())->rules()['description'], 'description'],
            'payroll' => [(new StorePayrollRequest())->rules()['notes'], 'notes'],
            'employee_transfer' => [(new StoreEmployeeTransferRequest())->rules()['reason'], 'reason'],
            'doc_type' => [(new StoreEmployeeDocumentTypeRequest())->rules()['description'], 'description'],
        ];

        foreach ($requestClasses as $name => [$rule, $field]) {
            $pass = Validator::make([$field => $longRichHtml], [$field => $rule]);
            $this->assertTrue($pass->passes(), "Expected {$name}.{$field} to accept 3k+ HTML payload.");

            $tooLong = Validator::make([$field => $over20kHtml], [$field => $rule]);
            $this->assertTrue($tooLong->fails(), "Expected {$name}.{$field} to reject >20k HTML payload.");
        }
    }

    public function test_coupon_and_contract_comment_controllers_sanitize_and_isolate_tenants(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $this->withoutMiddleware([
            \App\Http\Middleware\CheckInstallation::class,
            \App\Http\Middleware\TenantDomainMiddleware::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\DemoModeMiddleware::class,
            \App\Http\Middleware\UpdateUserActiveStatus::class,
            \App\Http\Middleware\PlanModuleCheck::class,
            \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);

        $dirtyCouponDesc = '<p><strong>Eid Discount</strong><script>fetch("/steal")</script></p>';

        $response = $this->actingAs($this->company1)
            ->post(route('coupons.store'), [
                'name' => 'Holiday Promo',
                'description' => $dirtyCouponDesc,
                'code' => 'EID2026',
                'discount' => 15,
                'type' => 'percentage',
                'status' => true,
            ]);

        $response->assertRedirect(route('coupons.index'));

        $coupon = Coupon::where('code', 'EID2026')->firstOrFail();
        $this->assertStringContainsString('<strong>Eid Discount</strong>', $coupon->description);
        $this->assertStringNotContainsString('<script', $coupon->description);
        $this->assertSame(1501, (int) $coupon->created_by);

        // ContractCommentController rejects XSS-only payload that sanitizes to empty and persists valid rich text
        $contract = Contract::create([
            'subject' => 'Rich Text MSA',
            'value' => 25000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'description' => HtmlSanitizer::sanitize('<p>Initial terms</p><img src="x" onerror="alert(1)">'),
            'status' => 'active',
            'source_type' => 'contract',
            'creator_id' => 1501,
            'created_by' => 1501,
        ]);

        $this->assertStringNotContainsString('onerror', $contract->description);

        $emptyXssResp = $this->actingAs($this->company1)
            ->post(route('contract-comments.store', $contract), [
                'comment' => '<script>alert("xss")</script>',
            ]);

        $emptyXssResp->assertSessionHasErrors('comment');
        $this->assertEquals(0, ContractComment::count());

        $validResp = $this->actingAs($this->company1)
            ->post(route('contract-comments.store', $contract), [
                'comment' => '<p onclick="evil()">Approved <em>amendment</em></p>',
            ]);

        $validResp->assertSessionHasNoErrors();
        $this->assertEquals(1, ContractComment::count());
        $savedComment = ContractComment::firstOrFail();
        $this->assertStringContainsString('<em>amendment</em>', $savedComment->comment);
        $this->assertStringNotContainsString('onclick', $savedComment->comment);

        // Tenant 2 cannot see Tenant 1's coupon or contract comment
        Auth::login($this->company2);
        Once::flush();

        $this->assertEquals(0, Coupon::count());
        $this->assertEquals(0, ContractComment::count());
    }

    public function test_waybill_blade_view_sanitizes_rich_text_notes(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $from = Warehouse::create(['name' => 'Addis Main', 'address' => 'Bole', 'city' => 'Addis Ababa', 'created_by' => 1501]);
        $to = Warehouse::create(['name' => 'Adama Hub', 'address' => 'Kebele 04', 'city' => 'Adama', 'created_by' => 1501]);

        $transfer = new Transfer([
            'waybill_number' => 'WB-202611-TEST1',
            'status' => 'in_transit',
            'quantity' => 10,
            'damaged_quantity' => 0,
            'date' => '2026-11-28',
            'driver_name' => 'Abebe Kebede',
            'notes' => '<p onclick="bad()">Fragile glass<script>evil()</script> <strong>handle upright</strong></p>',
        ]);
        $transfer->setRelation('fromWarehouse', $from);
        $transfer->setRelation('toWarehouse', $to);
        $transfer->setRelation('product', (object) ['name' => 'Server Rack', 'sku' => 'SRV-01']);

        $html = view('transfers.waybill', [
            'transfer' => $transfer,
            'companyName' => 'Gadaa Logistics',
            'companyVat' => 'ET-TIN-009911',
        ])->render();

        $this->assertStringContainsString('<strong>handle upright</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick="bad()"', $html);
        $this->assertStringNotContainsString('evil()', $html);
    }
}
