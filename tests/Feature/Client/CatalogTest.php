<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;

it('retorna categorias ativas da company', function () {
    $company = Company::factory()->create();
    $active = Category::factory()->for($company)->create(['is_active' => true]);
    Category::factory()->for($company)->create(['is_active' => false]);

    $this->withHeader('X-Company-ID', $company->id)
         ->getJson('/api/client/categories')
         ->assertOk()
         ->assertJsonCount(1, 'data')
         ->assertJsonPath('data.0.id', $active->id)
         ->assertJsonStructure(['data', 'links', 'meta']);
});

it('busca produtos por nome', function () {
    $company = Company::factory()->create();
    $category = Category::factory()->for($company)->create();

    Product::factory()->for($company)->for($category)->create([
        'name' => 'Banana Nanica',
        'slug' => 'banana-nanica',
        'is_active' => true,
    ]);

    Product::factory()->for($company)->for($category)->create([
        'name' => 'Tomate',
        'slug' => 'tomate',
        'is_active' => true,
    ]);

    $this->withHeader('X-Company-ID', $company->id)
        ->getJson('/api/client/products/search?q=banana')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Banana Nanica');
});
