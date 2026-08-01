<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'exists:services,id'],
            'delivery_zone_id' => ['nullable', 'exists:delivery_zones,id'],
            'ai_request_draft_id' => ['nullable', 'exists:ai_request_drafts,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'pickup_address' => ['required', 'string', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'package_description' => ['nullable', 'string'],
            'product_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_to_collect' => ['nullable', 'numeric', 'min:0'],
            'proposed_price' => ['nullable', 'numeric', 'min:0'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
