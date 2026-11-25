<?php

namespace Workdo\Recruitment\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Workdo\Recruitment\Events\ConvertOfferToEmployee;
use Workdo\Recruitment\Models\Offer;

class RecruitmentHrmIntegrationService
{
    public function isHrmActive(): bool
    {
        return function_exists('Module_is_active')
            && Module_is_active('Hrm')
            && class_exists(\Workdo\Hrm\Models\Employee::class);
    }

    public function getBranches(?int $companyId = null)
    {
        if (!$this->isHrmActive() || !class_exists(\Workdo\Hrm\Models\Branch::class)) {
            return collect();
        }
        $cid = $companyId ?? (function_exists('creatorId') ? creatorId() : 1);
        return \Workdo\Hrm\Models\Branch::where('created_by', $cid)->select('id', 'branch_name')->get();
    }

    public function getDepartments(?int $companyId = null)
    {
        if (!$this->isHrmActive() || !class_exists(\Workdo\Hrm\Models\Department::class)) {
            return collect();
        }
        $cid = $companyId ?? (function_exists('creatorId') ? creatorId() : 1);
        return \Workdo\Hrm\Models\Department::where('created_by', $cid)->select('id', 'department_name', 'branch_id')->get();
    }

    public function getDesignations(?int $companyId = null)
    {
        if (!$this->isHrmActive() || !class_exists(\Workdo\Hrm\Models\Designation::class)) {
            return collect();
        }
        $cid = $companyId ?? (function_exists('creatorId') ? creatorId() : 1);
        return \Workdo\Hrm\Models\Designation::where('created_by', $cid)->select('id', 'designation_name', 'branch_id', 'department_id')->get();
    }

    public function getShifts(?int $companyId = null)
    {
        if (!$this->isHrmActive() || !class_exists(\Workdo\Hrm\Models\Shift::class)) {
            return collect();
        }
        $cid = $companyId ?? (function_exists('creatorId') ? creatorId() : 1);
        return \Workdo\Hrm\Models\Shift::where('created_by', $cid)->select('id', 'shift_name')->get();
    }

    public function getDocumentTypes(?int $companyId = null)
    {
        if (!$this->isHrmActive() || !class_exists(\Workdo\Hrm\Models\EmployeeDocumentType::class)) {
            return collect();
        }
        $cid = $companyId ?? (function_exists('creatorId') ? creatorId() : 1);
        return \Workdo\Hrm\Models\EmployeeDocumentType::where('created_by', $cid)->select('id', 'document_name', 'is_required')->get();
    }

    public function generateEmployeeId(): string
    {
        if ($this->isHrmActive() && method_exists(\Workdo\Hrm\Models\Employee::class, 'generateEmployeeId')) {
            return \Workdo\Hrm\Models\Employee::generateEmployeeId();
        }
        return 'EMP-' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Atomically convert an accepted Offer into a User + HRM Employee record.
     */
    public function convertOfferToEmployee(Request $request, Offer $offer)
    {
        if (!$this->isHrmActive()) {
            throw new \Exception(__('HRM module must be active to convert an offer to an employee.'));
        }

        if ($offer->converted_to_employee) {
            throw new \Exception(__('Offer already converted to employee.'));
        }

        if (function_exists('canCreateUser')) {
            $checkUser = canCreateUser();
            if (is_array($checkUser) && empty($checkUser['can_create'])) {
                throw new \Exception($checkUser['message'] ?? __('User seat limit reached for your subscription plan.'));
            }
        }

        $companyId = function_exists('creatorId') ? creatorId() : $offer->created_by;

        return DB::transaction(function () use ($request, $offer, $companyId) {
            $genderMap = [
                '0' => 'Male',
                '1' => 'Female',
                '2' => 'Other',
            ];
            $genderText = $genderMap[$request->gender] ?? 'Male';

            if (User::where('email', $request->candidate_email)->exists()) {
                throw new \Exception(__('A user with this email address already exists.'));
            }

            $user = new User();
            $user->name = $request->candidate_name;
            $user->email = $request->candidate_email;
            $user->mobile_no = $request->phone_number;
            $user->password = bcrypt($request->password);
            $user->type = 'staff';
            $user->is_enable_login = true;
            $user->lang = function_exists('company_setting') ? (company_setting('defaultLanguage') ?? 'en') : 'en';
            $user->creator_id = Auth::id() ?? $companyId;
            $user->created_by = $companyId;
            $user->email_verified_at = now();
            $user->save();

            // Tenant-scoped staff role assignment
            $staffRole = Role::where('name', 'staff')
                ->where('created_by', $companyId)
                ->first();
            if ($staffRole) {
                $user->assignRole($staffRole);
            } else {
                $fallbackRole = Role::where('name', 'staff')->first();
                if ($fallbackRole) {
                    $user->assignRole($fallbackRole);
                }
            }

            $employeeClass = \Workdo\Hrm\Models\Employee::class;
            $employee = new $employeeClass();
            $employee->employee_id = $request->employee_id;
            $employee->date_of_birth = $request->date_of_birth;
            $employee->gender = $genderText;
            $employee->shift = $request->shift_id;
            $employee->date_of_joining = $request->date_of_joining;
            $employee->employment_type = $request->employment_type;
            $employee->address_line_1 = $request->address_line_1;
            $employee->address_line_2 = $request->address_line_2;
            $employee->city = $request->city;
            $employee->state = $request->state;
            $employee->country = $request->country;
            $employee->postal_code = $request->postal_code;
            $employee->emergency_contact_name = $request->emergency_contact_name;
            $employee->emergency_contact_relationship = $request->emergency_contact_relationship;
            $employee->emergency_contact_number = $request->emergency_contact_number;
            $employee->bank_name = $request->bank_name;
            $employee->account_holder_name = $request->account_holder_name;
            $employee->account_number = $request->account_number;
            $employee->bank_identifier_code = $request->bank_identifier_code;
            $employee->bank_branch = $request->bank_branch;
            $employee->tax_payer_id = $request->tax_payer_id;
            $employee->user_id = $user->id;
            $employee->branch_id = $request->branch_id;
            $employee->department_id = $request->department_id;
            $employee->designation_id = $request->designation_id;
            $employee->basic_salary = $request->basic_salary;
            $employee->hours_per_day = $request->hours_per_day;
            $employee->days_per_week = $request->days_per_week;
            $employee->rate_per_hour = $request->rate_per_hour;
            $employee->creator_id = Auth::id() ?? $companyId;
            $employee->created_by = $companyId;
            $employee->save();

            // Store documents if present
            if ($request->has('documents') && class_exists(\Workdo\Hrm\Models\EmployeeDocument::class)) {
                foreach ($request->input('documents', []) as $index => $document) {
                    if ($request->hasFile("documents.{$index}.file") && !empty($document['document_type_id'])) {
                        $file = $request->file("documents.{$index}.file");
                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension = $file->getClientOriginalExtension();
                        $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                        if (function_exists('upload_file')) {
                            $upload = upload_file($request, "documents.{$index}.file", $fileNameToStore, 'employee_documents');
                            if (isset($upload['flag']) && $upload['flag'] == 1 && isset($upload['url'])) {
                                \Workdo\Hrm\Models\EmployeeDocument::create([
                                    'user_id' => $employee->id,
                                    'document_type_id' => $document['document_type_id'],
                                    'file_path' => $upload['url'],
                                    'creator_id' => Auth::id() ?? $companyId,
                                    'created_by' => $companyId,
                                ]);
                            }
                        }
                    }
                }
            }

            $offer->update([
                'converted_to_employee' => true,
                'employee_id' => $employee->id,
            ]);

            $candidate = $offer->candidate;
            if ($candidate && $candidate->status === '3') {
                $candidate->status = '4';
                $candidate->save();
            }

            ConvertOfferToEmployee::dispatch($request, $offer, $employee);

            return $employee;
        });
    }
}
