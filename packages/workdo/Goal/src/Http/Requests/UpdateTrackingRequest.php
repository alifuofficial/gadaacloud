<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_id' => [
                'sometimes',
                'required',
                Rule::exists('goals', 'id')->where(fn($q) => $q->where('created_by', creatorId())),
            ],
            'tracking_date' => 'sometimes|date',
            'previous_amount' => 'sometimes|numeric|min:0',
            'contribution_amount' => 'sometimes|numeric|min:0',
            'current_amount' => 'sometimes|numeric|min:0',
            'progress_percentage' => 'sometimes|numeric|min:0|max:100',
            'days_remaining' => 'sometimes|integer',
            'projected_completion_date' => 'nullable|date',
            'on_track_status' => 'sometimes|in:ahead,on_track,behind,critical',
        ];
    }
}
