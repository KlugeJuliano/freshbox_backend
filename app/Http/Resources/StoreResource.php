<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'instagram' => $this->instagram,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'address' => [
                'street' => $this->address_street,
                'number' => $this->address_number,
                'complement' => $this->address_complement,
                'neighborhood' => $this->address_neighborhood,
                'city' => $this->address_city,
                'state' => $this->address_state,
                'zip' => $this->address_zip,
                'lat' => $this->address_lat !== null ? (float) $this->address_lat : null,
                'lng' => $this->address_lng !== null ? (float) $this->address_lng : null,
            ],
            'delivery_fee' => (float) $this->delivery_fee,
            'min_order_value' => (float) $this->min_order_value,
            'delivery_radius_km' => $this->delivery_radius_km,
            'business_hours' => $this->business_hours,
            'is_active' => $this->is_active,
            'accepts_delivery' => $this->accepts_delivery,
            'accepts_pickup' => $this->accepts_pickup,
            'open_now' => $this->isOpenNow(),
        ];
    }
}
