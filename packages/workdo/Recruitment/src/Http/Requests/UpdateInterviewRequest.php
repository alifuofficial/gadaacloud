<?php

namespace Workdo\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class UpdateInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', RecruitmentControllerHelpers::tenantExists('candidates', 'id')],
            'round_id' => ['required', RecruitmentControllerHelpers::tenantExists('interview_rounds', 'id')],
            'interview_type_id' => ['required', RecruitmentControllerHelpers::tenantExists('interview_types', 'id')],
            'scheduled_date' => 'required|date|after_or_equal:today',
            'scheduled_time' => 'required',
            'duration' => 'required|integer|min:1',
            'location' => 'nullable|max:255',
            'meeting_link' => 'nullable',
            'interviewer_ids' => 'nullable|array',
            'interviewer_ids.*' => [RecruitmentControllerHelpers::tenantExists('users', 'id')],
            'status' => 'required',

        ];
    }
}