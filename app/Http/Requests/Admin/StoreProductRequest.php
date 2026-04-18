<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('company_id', app('current_company')->id)
                ),
            ],
            'unit' => ['required', Rule::in(['kg', 'un', 'bandeja', 'maço', 'cx', 'lt', 'pct'])],
            'price' => ['required', 'numeric', 'min:0.01'],
            'promo_price' => ['nullable', 'numeric', 'min:0.01', 'lt:price'],
            'promo_ends_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
