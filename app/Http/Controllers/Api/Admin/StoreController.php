<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Services\ImageService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    private function store()
    {
        return app('current_company')->stores()->firstOrFail();
    }

    public function show(): StoreResource
    {
        return new StoreResource($this->store());
    }

    public function update(Request $request): StoreResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'instagram' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'delivery_radius_km' => ['nullable', 'integer', 'min:1'],
            'accepts_delivery' => ['sometimes', 'boolean'],
            'accepts_pickup' => ['sometimes', 'boolean'],
            'business_hours' => ['nullable', 'array'],
            'address_street' => ['nullable', 'string'],
            'address_number' => ['nullable', 'string'],
            'address_complement' => ['nullable', 'string'],
            'address_neighborhood' => ['nullable', 'string'],
            'address_city' => ['nullable', 'string'],
            'address_state' => ['nullable', 'string', 'max:2'],
            'address_zip' => ['nullable', 'string', 'max:9'],
        ]);

        $this->store()->update($data);

        return new StoreResource($this->store()->fresh());
    }

    public function uploadLogo(Request $request, ImageService $imageService)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $url = $imageService->uploadLogo($request->file('logo'), app('current_company')->id);

        $this->store()->update(['logo_url' => $url]);

        return response()->json(['logo_url' => $url]);
    }
}
