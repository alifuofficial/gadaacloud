<?php

namespace Workdo\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class ConvertToEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'candidate_name' => 'required|string|max:255',
            'candidate_email' => 'required|email|max:255|unique:users,email',
            'phone_number' => RecruitmentControllerHelpers::phoneRule(true),
            'employee_id' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:0,1,2',
            'password' => 'required|string|min:6',
            'shift_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('shifts', 'id')],
            'date_of_joining' => 'required|date',
            'employment_type' => 'required|string|max:255',
            'branch_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('departments', 'id')],
            'designation_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('designations', 'id')],
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_relationship' => 'required|string|max:255',
            'emergency_contact_number' => RecruitmentControllerHelpers::phoneRule(true),
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'bank_identifier_code' => 'required|string|max:255',
            'bank_branch' => 'required|string|max:255',
            'tax_payer_id' => 'nullable|string|max:255',
            'basic_salary' => 'required|numeric|min:0|max:99999999.99',
            'hours_per_day' => 'required|numeric|min:0|max:999999.99',
            'days_per_week' => 'required|numeric|min:0|max:999999.99',
            'rate_per_hour' => 'required|numeric|min:0|max:999999.99',
            'documents.*.document_type_id' => ['nullable', 'integer', RecruitmentControllerHelpers::tenantExists('employee_document_types', 'id')],
            'documents.*.file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ];
    }
}
