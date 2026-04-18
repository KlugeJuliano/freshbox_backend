<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\ImageService;

it('exibe dashboard e gerencia configuracoes da loja no admin', function () {
    [$company] = actingAsAdmin();
    $store = Store::factory()->for($company)->create(['name' => 'Loja Centro']);
    Category::factory()->count(2)->for($company)->create();
    Product::factory()->count(3)->for($company)->create();
    Banner::factory()->count(2)->for($company)->create();
    Order::factory()->for($company)->for($store)->create(['status' => 'new']);

    $this->getJson('/api/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('categories', 2)
        ->assertJsonPath('products', 3)
        ->assertJsonPath('banners', 2)
        ->assertJsonPath('orders.new', 1);

    $this->getJson('/api/admin/store')
        ->assertOk()
        ->assertJsonPath('data.name', 'Loja Centro')
        ->assertJsonStructure(['data' => ['address', 'open_now']]);

    $this->putJson('/api/admin/store', [
        'name' => 'Loja Atualizada',
        'delivery_fee' => 12.5,
        'address_city' => 'Curitiba',
        'accepts_pickup' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Loja Atualizada')
        ->assertJsonPath('data.delivery_fee', 12.5)
        ->assertJsonPath('data.address.city', 'Curitiba')
        ->assertJsonPath('data.accepts_pickup', false);
});

it('faz upload de logo com image service mockado', function () {
    [$company] = actingAsAdmin();
    Store::factory()->for($company)->create();

    $mock = \Mockery::mock(ImageService::class);
    $mock->shouldReceive('uploadLogo')
        ->once()
        ->andReturn('https://cdn.test/logo.jpg');
    app()->instance(ImageService::class, $mock);

    $this->withHeader('Accept', 'application/json')
        ->post('/api/admin/store/logo', [
            'logo' => fakePngUpload('logo.png'),
        ])
        ->assertOk()
        ->assertJsonPath('logo_url', 'https://cdn.test/logo.jpg');
});
