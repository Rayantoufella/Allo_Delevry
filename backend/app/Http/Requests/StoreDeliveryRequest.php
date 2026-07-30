<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'pickup_address' => ['required', 'string'],
            'delivery_address' => ['required', 'string'],
            'package_description' => ['nullable', 'string'],
            'product_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_to_collect' => ['nullable', 'numeric', 'min:0'],
            'proposed_price' => ['nullable', 'numeric', 'min:0'],
            'service_id' => ['nullable', 'exists:services,id'],
            'delivery_zone_id' => ['nullable', 'exists:delivery_zones,id'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
