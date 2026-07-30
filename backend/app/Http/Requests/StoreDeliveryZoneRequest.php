<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin_zone' => ['required', 'string', 'max:255'],
            'destination_zone' => ['required', 'string', 'max:255'],
            'fixed_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
