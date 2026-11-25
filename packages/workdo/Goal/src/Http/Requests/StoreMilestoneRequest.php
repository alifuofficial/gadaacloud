<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalMilestone;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_id' => [
                'required',
                Rule::exists('goals', 'id')->where(fn($q) => $q->where('created_by', creatorId())),
            ],
            'milestone_name' => 'required|string|max:255',
            'milestone_description' => 'nullable|string',
            'target_amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $goal = Goal::find($this->goal_id);
                    if ($goal) {
                        $existingTotal = GoalMilestone::where('goal_id', $this->goal_id)->sum('target_amount');
                        if (($existingTotal + $value) > $goal->target_amount) {
                            $fail(__('Total milestone amounts cannot exceed goal target amount of :amount', ['amount' => $goal->target_amount]));
                        }
                    }
                }
            ],
            'target_date' => 'required|date',
            'status' => 'required|in:pending,achieved,overdue',
        ];
    }
}
