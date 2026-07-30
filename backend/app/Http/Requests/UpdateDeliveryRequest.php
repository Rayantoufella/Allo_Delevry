<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'recipient_phone' => ['sometimes', 'string', 'max:20'],
            'pickup_address' => ['sometimes', 'string'],
            'delivery_address' => ['sometimes', 'string'],
            'package_description' => ['sometimes', 'nullable', 'string'],
            'product_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_to_collect' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'proposed_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'service_id' => ['sometimes', 'nullable', 'exists:services,id'],
            'delivery_zone_id' => ['sometimes', 'nullable', 'exists:delivery_zones,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
