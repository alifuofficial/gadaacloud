<?php

namespace Workdo\Hrm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitise inputs before validation.
     *
     * The employee-create form sends manager_id = 'none' when no manager is selected.
     * On Postgres the literal string 'none' would otherwise hit the `exists:employees,id`
     * rule with a non-numeric value, producing SQLSTATE[22P02]: invalid input syntax
     * for type bigint — and abort the whole request with HTTP 500 before the controller's
     * own 'none' => null coercion (EmployeeController::store()) ever runs.
     */
    protected function prepareForValidation(): void
    {
        if (in_array($this->input('manager_id'), ['', 'none', 'null'], true)) {
            $this->merge(['manager_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|max:50',
            'date_of_birth' => 'required|date',
            'gender' => 'required',
            'shift_id' => 'required|exists:shifts,id',
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
            'hours_per_day' => 'required|numeric|min:0|max:24',
            'days_per_week' => 'required|numeric|min:0|max:7',
            'rate_per_hour' => 'required|numeric|min:0',
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'manager_id' => 'nullable|exists:employees,id',
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => 'required_with:documents.*.file|exists:employee_document_types,id',
            'documents.*.file' => 'required_with:documents.*.document_type_id|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048'
        ];
    }
}