<?php

use App\Models\Order;
use App\Models\Store;

it('lista e exibe pedidos no admin e marca completed com timestamps', function () {
    [$company] = actingAsAdmin();
    $store = Store::factory()->for($company)->create();
    $order = Order::factory()->for($company)->for($store)->create([
        'status' => 'new',
        'confirmed_at' => null,
        'completed_at' => null,
    ]);
    $order->items()->create([
        'product_name' => 'Banana',
        'product_unit' => 'kg',
        'unit_price' => 5,
        'quantity' => 2,
        'subtotal' => 10,
    ]);

    $this->getJson('/api/admin/orders?status=new')
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id);

    $this->getJson("/api/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.product_name', 'Banana');

    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => 'completed',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJson(fn ($json) => $json
            ->whereType('data.confirmed_at', 'string')
            ->whereType('data.completed_at', 'string')
            ->etc()
        );
});
