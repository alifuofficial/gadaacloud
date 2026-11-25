<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalMilestone;

class UpdateMilestoneRequest extends FormRequest
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
            'milestone_name' => 'required|string|max:255',
            'milestone_description' => 'nullable|string',
            'target_amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $goalId = $this->input('goal_id');
                    $milestone = $this->route('milestone');
                    $milestoneId = is_object($milestone) ? $milestone->id : $milestone;

                    if (!$goalId && $milestone) {
                        $milestoneObj = is_object($milestone) ? $milestone : GoalMilestone::find($milestone);
                        $goalId = $milestoneObj?->goal_id;
                    }

                    if ($goalId) {
                        $goal = Goal::find($goalId);
                        if ($goal) {
                            $existingTotal = GoalMilestone::where('goal_id', $goalId)
                                ->when($milestoneId, fn($q) => $q->where('id', '!=', $milestoneId))
                                ->sum('target_amount');
                            if (($existingTotal + $value) > $goal->target_amount) {
                                $fail(__('Total milestone amounts cannot exceed goal target amount of :amount', ['amount' => $goal->target_amount]));
                            }
                        }
                    }
                }
            ],
            'target_date' => 'required|date',
            'status' => 'required|in:pending,achieved,overdue',
            'achieved_amount' => 'nullable|numeric|min:0',
        ];
    }
}
