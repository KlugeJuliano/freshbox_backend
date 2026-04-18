<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;

it('não vaza dados entre companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $category = Category::factory()->for($companyA)->create();

    $product = Product::factory()->for($companyA)->for($category)->create([
        'slug' => 'produto-secreto',
    ]);

    $response = $this->withHeader('X-Company-ID', $companyB->id)
        ->getJson("/api/client/products/{$product->slug}");

    $response->assertNotFound();
});
