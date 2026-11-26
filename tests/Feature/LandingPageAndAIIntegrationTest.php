<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Workdo\AIAssistant\Models\AIPrompt;
use Workdo\LandingPage\Models\CustomPage;
use Workdo\LandingPage\Models\NewsletterSubscriber;

class LandingPageAndAIIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('subscribed_at');
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country_code')->nullable();
            $table->string('isp')->nullable();
            $table->string('org')->nullable();
            $table->string('timezone')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('module')->nullable();
            $table->string('submodule')->nullable();
            $table->string('field_type');
            $table->text('prompt_template');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function test_landing_page_models_and_ai_prompt_hierarchy_work_on_sqlite(): void
    {
        $page = CustomPage::create([
            'title' => 'Enterprise Privacy Policy',
            'slug' => 'enterprise-privacy-policy',
            'content' => '<p>Compliant policy text.</p>',
            'is_active' => true,
        ]);
        $this->assertEquals('enterprise-privacy-policy', $page->slug);
        $this->assertTrue($page->is_active);

        $subscriber = NewsletterSubscriber::create([
            'email' => 'architect@gadaacloud.com',
            'subscribed_at' => now(),
            'country' => 'Ethiopia',
            'city' => 'Addis Ababa',
        ]);
        $this->assertEquals('Ethiopia', $subscriber->country);

        AIPrompt::create([
            'module' => 'general',
            'submodule' => null,
            'field_type' => 'summary',
            'prompt_template' => 'General summary prompt',
            'status' => true,
        ]);
        AIPrompt::create([
            'module' => 'Sales',
            'submodule' => null,
            'field_type' => 'summary',
            'prompt_template' => 'Sales module prompt',
            'status' => true,
        ]);
        AIPrompt::create([
            'module' => 'Sales',
            'submodule' => 'proposal',
            'field_type' => 'summary',
            'prompt_template' => 'Proposal specific prompt',
            'status' => true,
        ]);

        $this->assertEquals('Proposal specific prompt', AIPrompt::getPrompt('Sales', 'summary', 'proposal'));
        $this->assertEquals('Sales module prompt', AIPrompt::getPrompt('Sales', 'summary', 'unconfigured_sub'));
        $this->assertEquals('General summary prompt', AIPrompt::getPrompt('CRM', 'summary'));
        $this->assertNotEmpty(AIPrompt::getPrompt('CRM', 'unknown_field'));
    }
}
