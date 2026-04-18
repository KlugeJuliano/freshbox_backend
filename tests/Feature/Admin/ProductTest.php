<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('cria produto com slug unico por company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $category = Category::factory()->for($company)->create();

    Sanctum::actingAs($user, ['admin']);

    $payload = [
        'name' => 'Banana Prata',
        'category_id' => $category->id,
        'unit' => 'kg',
        'price' => 9.5,
        'is_active' => true,
        'is_available' => true,
    ];

    $this->postJson('/api/admin/products', $payload)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'banana-prata');

    $this->postJson('/api/admin/products', $payload)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'banana-prata-1');
});
