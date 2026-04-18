<?php

namespace Database\Factories;

use App\Models\Banner;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(),
            'image_url' => fake()->imageUrl(),
            'image_mobile_url' => fake()->imageUrl(),
            'link_type' => 'none',
            'link_value' => null,
            'priority' => fake()->numberBetween(0, 5),
            'period_start' => now()->subDay(),
            'period_end' => now()->addDay(),
            'is_active' => true,
        ];
    }
}
