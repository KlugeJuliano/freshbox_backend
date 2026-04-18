<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\ImageService;

it('lista, mostra, atualiza, alterna e remove produtos no admin', function () {
    [$company] = actingAsAdmin();
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create([
        'name' => 'Banana Ouro',
        'slug' => 'banana-ouro',
        'is_active' => true,
    ]);

    $this->getJson('/api/admin/products?search=banana&active=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $product->id);

    $this->getJson("/api/admin/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.slug', 'banana-ouro');

    $this->putJson("/api/admin/products/{$product->id}", [
        'name' => 'Banana da Terra',
        'category_id' => $category->id,
        'unit' => 'kg',
        'price' => 12.4,
        'is_available' => true,
        'is_active' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'banana-da-terra');

    $this->patchJson("/api/admin/products/{$product->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/admin/products/{$product->id}")
        ->assertNoContent();
});

it('faz upload de imagem de produto com service mockado', function () {
    [$company] = actingAsAdmin();
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create();

    $mock = \Mockery::mock(ImageService::class);
    $mock->shouldReceive('processProductImage')
        ->once()
        ->andReturn([
            'image_thumb_url' => 'https://cdn.test/thumb.jpg',
            'image_card_url' => 'https://cdn.test/card.jpg',
            'image_full_url' => 'https://cdn.test/full.jpg',
        ]);
    app()->instance(ImageService::class, $mock);

    $this->withHeader('Accept', 'application/json')
        ->post("/api/admin/products/{$product->id}/image", [
            'image' => fakePngUpload('product.png'),
        ])
        ->assertOk()
        ->assertJsonPath('data.images.thumb', 'https://cdn.test/thumb.jpg')
        ->assertJsonPath('data.images.card', 'https://cdn.test/card.jpg')
        ->assertJsonPath('data.images.full', 'https://cdn.test/full.jpg');
});
