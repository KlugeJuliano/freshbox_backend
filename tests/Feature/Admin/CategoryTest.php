<?php

use App\Models\Category;

it('gerencia categorias no admin', function () {
    [$company] = actingAsAdmin();

    $storeResponse = $this->postJson('/api/admin/categories', [
        'name' => 'Folhas',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $categoryId = $storeResponse->json('data.id');

    $storeResponse
        ->assertCreated()
        ->assertJsonPath('data.slug', 'folhas');

    $this->getJson('/api/admin/categories')
        ->assertOk()
        ->assertJsonPath('data.0.id', $categoryId);

    $this->getJson("/api/admin/categories/{$categoryId}")
        ->assertOk()
        ->assertJsonPath('data.id', $categoryId);

    $this->putJson("/api/admin/categories/{$categoryId}", [
        'name' => 'Folhas Verdes',
        'sort_order' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'folhas-verdes')
        ->assertJsonPath('data.sort_order', 5);

    $this->patchJson("/api/admin/categories/{$categoryId}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $another = Category::factory()->for($company)->create(['sort_order' => 0]);

    $this->patchJson('/api/admin/categories/reorder', [
        'items' => [
            ['id' => $categoryId, 'sort_order' => 0],
            ['id' => $another->id, 'sort_order' => 1],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.0.id', $categoryId);

    $this->deleteJson("/api/admin/categories/{$categoryId}")
        ->assertNoContent();

    expect(Category::withTrashed()->find($categoryId)?->trashed())->toBeTrue();
});

it('bloqueia acesso a categoria de outra company', function () {
    [$company] = actingAsAdmin();
    $other = \App\Models\Company::factory()->create();
    $category = Category::factory()->for($other)->create();

    $this->getJson("/api/admin/categories/{$category->id}")
        ->assertForbidden();

    expect($company->id)->not->toBe($other->id);
});
