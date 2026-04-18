<?php

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('atualiza status do pedido no admin', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $order = Order::factory()->for($company)->create();

    Sanctum::actingAs($user, ['admin']);

    $this->patchJson("/api/admin/orders/{$order->id}/status", [
        'status' => 'preparing',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing');
});
