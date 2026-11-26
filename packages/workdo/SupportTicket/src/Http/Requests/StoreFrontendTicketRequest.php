<?php

namespace Workdo\SupportTicket\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Workdo\SupportTicket\Traits\SupportTicketControllerHelpers;

class StoreFrontendTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $slug = $this->route('slug');
        $companyId = $slug ? (User::where('slug', $slug)->value('id') ?? creatorId()) : creatorId();

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'category' => ['required', SupportTicketControllerHelpers::tenantExists('ticket_categories', 'id', (int) $companyId)],
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|string|in:In Progress,On Hold,Closed',
            'account_type' => 'required|string|in:custom',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'sometimes|file|max:10240'
        ];
    }
}