<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $company = Company::factory();

        return [
            'company_id' => $company,
            'category_id' => Category::factory()->state(fn () => ['company_id' => $company]),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'unit' => fake()->randomElement(['kg', 'un', 'bandeja', 'maço', 'cx', 'lt', 'pct']),
            'price' => fake()->randomFloat(2, 1, 30),
            'promo_price' => null,
            'promo_ends_at' => null,
            'is_active' => true,
            'is_featured' => false,
            'is_available' => true,
            'sort_order' => 0,
        ];
    }
}
