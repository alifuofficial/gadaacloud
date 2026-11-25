<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Employee;
use Workdo\Recruitment\Models\Candidate;
use Workdo\Recruitment\Models\JobPosting;
use Workdo\Recruitment\Models\Offer;
use Workdo\Recruitment\Services\RecruitmentHrmIntegrationService;

class RecruitmentIntegrationTest extends TestCase
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
            $table->string('mobile_no')->nullable();
            $table->boolean('is_enable_login')->default(true);
            $table->string('lang')->default('en');
            $table->timestamp('email_verified_at')->nullable();
            $table->text('active_module')->nullable();
            $table->unsignedBigInteger('active_plan')->default(0);
            $table->integer('total_user')->default(-1);
            $table->date('plan_expire_date')->nullable();
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

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
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
            $table->string('package_name')->default('Recruitment');
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        foreach (['Recruitment', 'Hrm'] as $mod) {
            DB::table('add_ons')->insert([
                'module'       => $mod,
                'name'         => $mod,
                'is_enable'    => 1,
                'package_name' => $mod,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('modules')->nullable();
            $table->integer('number_of_user')->default(-1);
            $table->timestamps();
        });

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Enterprise',
            'modules' => json_encode(['Recruitment', 'Hrm']),
            'number_of_user' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('user_active_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('posting_code')->nullable();
            $table->string('title');
            $table->string('position')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_published')->default(true);
            $table->date('application_deadline')->nullable();
            $table->string('ethiopian_fiscal_year', 30)->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('national_id', 60)->nullable();
            $table->string('status')->default('0');
            $table->date('application_date')->nullable();
            $table->string('ethiopian_fiscal_year', 30)->nullable();
            $table->string('tracking_id')->nullable();
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->date('offer_date')->nullable();
            $table->string('ethiopian_fiscal_year', 30)->nullable();
            $table->string('position')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('salary', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('status')->default('sent');
            $table->boolean('converted_to_employee')->default(false);
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('shift')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_identifier_code')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('tax_payer_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('hours_per_day', 8, 2)->default(8);
            $table->decimal('days_per_week', 8, 2)->default(5);
            $table->decimal('rate_per_hour', 15, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->company1 = User::forceCreate([
            'id' => 101,
            'name' => 'Company One',
            'email' => 'comp1@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'active_module' => 'Recruitment,Hrm',
            'active_plan' => 1,
            'total_user' => -1,
            'plan_expire_date' => now()->addYear()->toDateString(),
            'created_by' => 101,
        ]);

        $this->company2 = User::forceCreate([
            'id' => 102,
            'name' => 'Company Two',
            'email' => 'comp2@gadaacloud.com',
            'password' => bcrypt('password'),
            'type' => 'company',
            'active_module' => 'Recruitment,Hrm',
            'active_plan' => 1,
            'total_user' => -1,
            'plan_expire_date' => now()->addYear()->toDateString(),
            'created_by' => 102,
        ]);

        Role::create(['name' => 'staff', 'guard_name' => 'web', 'created_by' => 101]);
    }

    public function test_multi_tenant_isolation_on_job_postings_candidates_and_offers(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $job = JobPosting::create([
            'title' => 'Senior ERP Architect',
            'position' => 'Architect',
            'ethiopian_fiscal_year' => '2018 E.C.',
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $candidate = Candidate::create([
            'first_name' => 'Dawit',
            'last_name' => 'Tadesse',
            'email' => 'dawit@ethio.et',
            'phone' => '0911223344',
            'national_id' => 'FAYDA-998877',
            'status' => '3',
            'job_id' => $job->id,
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $offer = Offer::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'position' => 'Architect',
            'salary' => 45000,
            'status' => 'accepted',
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $this->assertCount(1, JobPosting::all());
        $this->assertCount(1, Candidate::all());
        $this->assertCount(1, Offer::all());

        // Switch to company 2
        Auth::login($this->company2);
        Once::flush();

        $this->assertCount(0, JobPosting::all());
        $this->assertCount(0, Candidate::all());
        $this->assertCount(0, Offer::all());
        $this->assertNull(Offer::find($offer->id));
    }

    public function test_atomic_offer_to_employee_conversion_and_candidate_hired_status(): void
    {
        Auth::login($this->company1);
        Once::flush();

        $job = JobPosting::create([
            'title' => 'Accountant',
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $candidate = Candidate::create([
            'first_name' => 'Sara',
            'last_name' => 'Bekele',
            'email' => 'sara.bekele@ethio.et',
            'phone' => '+251911334455',
            'status' => '3',
            'job_id' => $job->id,
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $offer = Offer::create([
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'position' => 'Senior Accountant',
            'salary' => 35000,
            'status' => 'accepted',
            'creator_id' => 101,
            'created_by' => 101,
        ]);

        $request = Request::create('/recruitment/offers/1/convert', 'POST', [
            'candidate_name' => 'Sara Bekele',
            'candidate_email' => 'sara.bekele@ethio.et',
            'phone_number' => '+251911334455',
            'employee_id' => 'EMP-001',
            'date_of_birth' => '1995-05-10',
            'gender' => '1',
            'password' => 'secret123',
            'shift_id' => 1,
            'date_of_joining' => '2026-12-01',
            'employment_type' => 'Full Time',
            'branch_id' => 1,
            'department_id' => 1,
            'designation_id' => 1,
            'address_line_1' => 'Bole Subcity',
            'city' => 'Addis Ababa',
            'state' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'postal_code' => '1000',
            'emergency_contact_name' => 'Bekele T',
            'emergency_contact_relationship' => 'Father',
            'emergency_contact_number' => '0911000000',
            'bank_name' => 'CBE',
            'account_holder_name' => 'Sara Bekele',
            'account_number' => '1000123456789',
            'bank_identifier_code' => 'CBETETAA',
            'bank_branch' => 'Bole',
            'tax_payer_id' => '0055667788',
            'basic_salary' => 35000,
            'hours_per_day' => 8,
            'days_per_week' => 5,
            'rate_per_hour' => 218.75,
        ]);

        $service = new RecruitmentHrmIntegrationService();
        $employee = $service->convertOfferToEmployee($request, $offer);

        $this->assertNotNull($employee);
        $this->assertEquals('EMP-001', $employee->employee_id);
        $this->assertEquals('Female', $employee->gender);
        $this->assertEquals('0055667788', $employee->tax_payer_id);

        $offer->refresh();
        $candidate->refresh();

        $this->assertTrue((bool) $offer->converted_to_employee);
        $this->assertEquals($employee->id, $offer->employee_id);
        $this->assertEquals('4', $candidate->status); // Hired
    }
}
