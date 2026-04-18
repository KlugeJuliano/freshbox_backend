<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Store;

it('cria pedido e retorna url do whatsapp', function () {
    $company = Company::factory()->create();
    Store::factory()->for($company)->create([
        'whatsapp' => '5541999999999',
        'min_order_value' => 0,
    ]);
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create([
        'price' => 10,
        'promo_price' => null,
        'is_active' => true,
        'is_available' => true,
    ]);

    $this->withHeader('X-Company-ID', $company->id)
        ->postJson('/api/client/orders', [
            'customer_name' => 'Maria',
            'customer_phone' => '41988887777',
            'delivery_type' => 'pickup',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonStructure(['order', 'whatsapp_url'])
        ->assertJsonPath('order.total', 20);
});

it('rejeita pedido abaixo do valor minimo', function () {
    $company = Company::factory()->create();
    Store::factory()->for($company)->create([
        'min_order_value' => 50,
    ]);
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create([
        'price' => 10,
        'promo_price' => null,
    ]);

    $this->withHeader('X-Company-ID', $company->id)
        ->postJson('/api/client/orders', [
            'customer_name' => 'Maria',
            'customer_phone' => '41988887777',
            'delivery_type' => 'pickup',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subtotal');
});
