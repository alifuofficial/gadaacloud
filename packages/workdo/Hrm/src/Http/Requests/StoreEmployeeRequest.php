<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitise inputs before validation.
     * Converts empty strings / 'none' / 'null' to null for optional foreign keys and nullable fields.
     */
    protected function prepareForValidation(): void
    {
        $fieldsToNullify = [
            'manager_id',
            'shift_id',
            'tax_payer_id',
            'address_line_2',
            'documents',
        ];

        $updates = [];
        foreach ($fieldsToNullify as $field) {
            $val = $this->input($field);
            if (in_array($val, ['', 'none', 'null', 'undefined'], true)) {
                $updates[$field] = null;
            }
        }

        if (!empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|max:50',
            'date_of_birth' => 'required|date',
            'gender' => 'required',
            'shift_id' => ['nullable', HrmControllerHelpers::tenantExists('shifts', 'id')],
            'date_of_joining' => 'required|date',
            'employment_type' => 'required',
            'address_line_1' => 'required|max:255',
            'address_line_2' => 'nullable|max:255',
            'city' => 'required|max:100',
            'state' => 'required|max:100',
            'country' => 'required|max:100',
            'postal_code' => 'required|max:20',
            'emergency_contact_name' => 'required|max:100',
            'emergency_contact_relationship' => 'required|max:100',
            'emergency_contact_number' => 'required|max:20',
            'bank_name' => 'required|max:100',
            'account_holder_name' => 'required|max:100',
            'account_number' => 'required|max:50',
            'bank_identifier_code' => 'required|max:50',
            'bank_branch' => 'required|max:100',
            'tax_payer_id' => 'nullable|max:50',
            'basic_salary' => 'required|numeric|min:0',
            'is_pension_applicable' => 'nullable|boolean',
            'is_tax_exempt' => 'nullable|boolean',
            'hours_per_day' => 'required|numeric|min:0|max:24',
            'days_per_week' => 'required|numeric|min:0|max:7',
            'rate_per_hour' => 'required|numeric|min:0',
            'user_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'branch_id' => ['required', HrmControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', HrmControllerHelpers::tenantExists('departments', 'id')],
            'designation_id' => ['required', HrmControllerHelpers::tenantExists('designations', 'id')],
            'manager_id' => ['nullable', HrmControllerHelpers::tenantExists('employees', 'id')],
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => ['required_with:documents.*.file', HrmControllerHelpers::tenantExists('employee_document_types', 'id')],
            'documents.*.file' => 'required_with:documents.*.document_type_id|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048'
        ];
    }
}