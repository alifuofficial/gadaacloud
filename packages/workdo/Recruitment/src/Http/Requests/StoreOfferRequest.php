<?php

namespace Workdo\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('candidates', 'id')],
            'job_id' => ['nullable', 'integer', RecruitmentControllerHelpers::tenantExists('job_postings', 'id')],
            'offer_date' => 'required|date',
            'ethiopian_fiscal_year' => 'nullable|string|max:30',
            'position' => 'required|string|max:255',
            'department_id' => ['required', 'integer', RecruitmentControllerHelpers::tenantExists('departments', 'id')],
            'salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'equity' => 'nullable',
            'benefits' => 'nullable',
            'start_date' => 'required|date',
            'expiration_date' => 'required|date',
            'offer_letter_path' => 'nullable',
            'status' => 'required|string',
            'response_date' => 'nullable|date',
            'decline_reason' => 'nullable|string',
            'approved_by' => ['nullable', 'integer', RecruitmentControllerHelpers::tenantExists('users', 'id')],
        ];
    }
}
