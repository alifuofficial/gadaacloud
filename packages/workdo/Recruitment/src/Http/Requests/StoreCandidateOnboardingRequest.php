<?php

namespace Workdo\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class StoreCandidateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', RecruitmentControllerHelpers::tenantExists('candidates', 'id')],
            'checklist_id' => ['required', RecruitmentControllerHelpers::tenantExists('onboarding_checklists', 'id')],
            'start_date' => 'required|date',
            'buddy_employee_id' => ['nullable', RecruitmentControllerHelpers::tenantExists('users', 'id')],
            'status' => 'nullable|in:Pending,In Progress,Completed'
        ];
    }
}