<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'store_id' => Store::factory()->state(fn () => ['company_id' => $company]),
            'customer_name' => fake()->name(),
            'customer_phone' => '41988887777',
            'delivery_type' => 'pickup',
            'subtotal' => 10,
            'delivery_fee' => 0,
            'total' => 10,
            'payment_method' => 'pix',
            'status' => 'new',
        ];
    }
}
