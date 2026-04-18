<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? ['required'] : ['nullable'];

        return [
            'title' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'image' => [...$required, 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'image_mobile' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'link_type' => ['nullable', Rule::in(['product', 'category', 'url', 'none'])],
            'link_value' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after:period_start'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
