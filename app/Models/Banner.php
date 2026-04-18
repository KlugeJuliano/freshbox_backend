<?php

namespace App\Models;

use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'subtitle',
        'image_url',
        'image_mobile_url',
        'link_type',
        'link_value',
        'priority',
        'period_start',
        'period_end',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('period_start')->orWhere('period_start', '<=', now());
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('period_end')->orWhere('period_end', '>=', now());
            })
            ->orderByDesc('priority');
    }
}
