<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'delivery_type' => ['required', 'in:delivery,pickup'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'observations' => ['nullable', 'string', 'max:300'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.observation' => ['nullable', 'string', 'max:100'],
            'address' => ['required_if:delivery_type,delivery', 'array'],
            'address.street' => ['required_if:delivery_type,delivery', 'string'],
            'address.number' => ['required_if:delivery_type,delivery', 'string'],
            'address.complement' => ['nullable', 'string'],
            'address.neighborhood' => ['required_if:delivery_type,delivery', 'string'],
            'address.city' => ['required_if:delivery_type,delivery', 'string'],
            'address.zip' => ['nullable', 'string', 'max:9'],
        ];
    }
}
