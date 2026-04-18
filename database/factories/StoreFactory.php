<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Hortifruti '.fake()->city(),
            'phone' => '4133334444',
            'whatsapp' => '5541999999999',
            'email' => fake()->safeEmail(),
            'instagram' => '@hortifruti',
            'description' => fake()->sentence(),
            'delivery_fee' => 5,
            'min_order_value' => 20,
            'delivery_radius_km' => 8,
            'business_hours' => [
                'mon' => ['open' => '08:00', 'close' => '18:00'],
                'tue' => ['open' => '08:00', 'close' => '18:00'],
                'wed' => ['open' => '08:00', 'close' => '18:00'],
                'thu' => ['open' => '08:00', 'close' => '18:00'],
                'fri' => ['open' => '08:00', 'close' => '18:00'],
                'sat' => ['open' => '08:00', 'close' => '14:00'],
                'sun' => null,
            ],
            'is_active' => true,
            'accepts_delivery' => true,
            'accepts_pickup' => true,
        ];
    }
}
