<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'promo_price' => $this->promo_price !== null ? (float) $this->promo_price : null,
            'promo_ends_at' => $this->promo_ends_at?->toIso8601String(),
            'is_on_promo' => $this->is_on_promo,
            'effective_price' => (float) $this->effective_price,
            'images' => [
                'thumb' => $this->image_thumb_url,
                'card' => $this->image_card_url,
                'full' => $this->image_full_url,
                'gallery' => $this->whenLoaded(
                    'images',
                    fn () => $this->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'label' => $image->label,
                        'sort_order' => $image->sort_order,
                    ])->values()
                ),
            ],
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
        ];
    }
}
