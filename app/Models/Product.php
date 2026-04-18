<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'slug',
        'description',
        'unit',
        'price',
        'promo_price',
        'promo_ends_at',
        'image_thumb_url',
        'image_card_url',
        'image_full_url',
        'is_active',
        'is_featured',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'promo_ends_at' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'effective_price',
        'is_on_promo',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function getEffectivePriceAttribute(): string
    {
        if ($this->promo_price !== null && (! $this->promo_ends_at || $this->promo_ends_at->isFuture())) {
            return (string) $this->promo_price;
        }

        return (string) $this->price;
    }

    public function getIsOnPromoAttribute(): bool
    {
        return $this->promo_price !== null
            && (! $this->promo_ends_at || $this->promo_ends_at->isFuture());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_available', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnPromo(Builder $query): Builder
    {
        return $query
            ->whereNotNull('promo_price')
            ->where(function (Builder $builder) {
                $builder->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>', now());
            });
    }
}
