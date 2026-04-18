<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_type' => $this->delivery_type,
            'delivery_address' => [
                'street' => $this->delivery_street,
                'number' => $this->delivery_number,
                'complement' => $this->delivery_complement,
                'neighborhood' => $this->delivery_neighborhood,
                'city' => $this->delivery_city,
                'zip' => $this->delivery_zip,
            ],
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'observations' => $this->observations,
            'status' => $this->status,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_unit' => $item->product_unit,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'observation' => $item->observation,
            ])->values()),
        ];
    }
}
