<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Store;

it('retorna store do cliente e protege company inexistente', function () {
    $company = Company::factory()->create();
    Store::factory()->for($company)->create(['name' => 'Loja Cliente']);

    $this->withHeader('X-Company-ID', $company->id)
        ->getJson('/api/client/store')
        ->assertOk()
        ->assertJsonPath('data.name', 'Loja Cliente')
        ->assertJsonStructure(['data' => ['address', 'open_now']]);

    $this->withHeader('X-Company-ID', '00000000-0000-0000-0000-000000000000')
        ->getJson('/api/client/store')
        ->assertNotFound();
});

it('lista produtos por categoria, detalhe, destaque e promocao no cliente', function () {
    $company = Company::factory()->create();
    Store::factory()->for($company)->create();
    $category = Category::factory()->for($company)->create([
        'slug' => 'frutas',
        'is_active' => true,
    ]);

    $featured = Product::factory()->for($company)->for($category)->create([
        'name' => 'Banana',
        'slug' => 'banana',
        'is_featured' => true,
        'is_active' => true,
        'is_available' => true,
    ]);

    Product::factory()->for($company)->for($category)->create([
        'name' => 'Maca Promo',
        'slug' => 'maca-promo',
        'promo_price' => 5.9,
        'promo_ends_at' => now()->addDay(),
        'is_active' => true,
        'is_available' => true,
    ]);

    $header = ['X-Company-ID' => $company->id];

    $this->withHeaders($header)
        ->getJson('/api/client/categories/frutas/products')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Banana']);

    $this->withHeaders($header)
        ->getJson('/api/client/products/banana')
        ->assertOk()
        ->assertJsonPath('data.id', $featured->id);

    $this->withHeaders($header)
        ->getJson('/api/client/products/featured')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Banana']);

    $this->withHeaders($header)
        ->getJson('/api/client/products/on-promo')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Maca Promo']);
});
