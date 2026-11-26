<?php

namespace Workdo\Recruitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class StoreInterviewRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'sequence_number' => 'required|min:1',
            'description' => 'nullable',
            'status' => 'required',
            'job_id' => ['required', RecruitmentControllerHelpers::tenantExists('job_postings', 'id')]
        ];
    }
}