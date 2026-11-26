<?php

namespace App\Http\Requests;

use App\Traits\ControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryRule = Auth::user()?->type === 'superadmin'
            ? ['required', Rule::exists('helpdesk_categories', 'id')]
            : ['required', ControllerHelpers::tenantExists('helpdesk_categories', 'id')];

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|in:open,in_progress,resolved,closed',
            'priority' => 'required|in:low,medium,high,urgent',
            'category_id' => $categoryRule,
        ];
    }
}