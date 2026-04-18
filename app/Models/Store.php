<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'whatsapp',
        'email',
        'instagram',
        'description',
        'logo_url',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zip',
        'address_lat',
        'address_lng',
        'delivery_fee',
        'min_order_value',
        'delivery_radius_km',
        'business_hours',
        'is_active',
        'accepts_delivery',
        'accepts_pickup',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'is_active' => 'boolean',
        'accepts_delivery' => 'boolean',
        'accepts_pickup' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpenNow(): bool
    {
        if (! $this->is_active || ! is_array($this->business_hours)) {
            return false;
        }

        $map = [
            'Monday' => 'mon',
            'Tuesday' => 'tue',
            'Wednesday' => 'wed',
            'Thursday' => 'thu',
            'Friday' => 'fri',
            'Saturday' => 'sat',
            'Sunday' => 'sun',
        ];
        $day = $map[now()->englishDayOfWeek] ?? null;
        $hours = $this->business_hours[$day] ?? null;

        if (! is_array($hours) || empty($hours['open']) || empty($hours['close'])) {
            return false;
        }

        $now = now()->format('H:i');

        return $now >= $hours['open'] && $now <= $hours['close'];
    }
}
