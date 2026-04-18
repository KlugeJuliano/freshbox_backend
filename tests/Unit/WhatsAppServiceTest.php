<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\WhatsAppService;

it('monta url do whatsapp com resumo do pedido', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Maria',
        'customer_phone' => '41988887777',
        'delivery_type' => 'pickup',
        'subtotal' => 20,
        'delivery_fee' => 0,
        'total' => 20,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => null,
        'product_name' => 'Banana Nanica',
        'product_unit' => 'kg',
        'unit_price' => 10,
        'quantity' => 2,
        'subtotal' => 20,
    ]);

    $url = app(WhatsAppService::class)->buildOrderUrl($order->fresh()->load(['items', 'store']));

    expect($url)
        ->toStartWith('https://wa.me/5541999999999?text=')
        ->and(urldecode($url))->toContain('Maria')
        ->and(urldecode($url))->toContain('Banana Nanica');
});
