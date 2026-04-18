<?php

use App\Models\Banner;
use App\Services\ImageService;

it('gerencia banners no admin e lista no cliente apenas ativos no periodo', function () {
    [$company] = actingAsAdmin();

    $mock = \Mockery::mock(ImageService::class);
    $mock->shouldReceive('uploadBannerImage')->times(2)->andReturn(
        'https://cdn.test/banner-main.jpg',
        'https://cdn.test/banner-mobile.jpg'
    );
    app()->instance(ImageService::class, $mock);

    $storeResponse = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/admin/banners', [
            'title' => 'Semana da fruta',
            'subtitle' => 'Ofertas',
            'image' => fakePngUpload('banner.png'),
            'image_mobile' => fakePngUpload('banner-mobile.png'),
            'priority' => 4,
            'period_start' => now()->subDay()->toIso8601String(),
            'period_end' => now()->addDay()->toIso8601String(),
            'is_active' => true,
        ]);

    $bannerId = $storeResponse->json('data.id');

    $storeResponse
        ->assertCreated()
        ->assertJsonPath('data.image_url', 'https://cdn.test/banner-main.jpg')
        ->assertJsonPath('data.image_mobile_url', 'https://cdn.test/banner-mobile.jpg');

    $this->getJson('/api/admin/banners')
        ->assertOk()
        ->assertJsonPath('data.0.id', $bannerId);

    $this->getJson("/api/admin/banners/{$bannerId}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Semana da fruta');

    $mockUpdate = \Mockery::mock(ImageService::class);
    $mockUpdate->shouldReceive('uploadBannerImage')->once()->andReturn('https://cdn.test/banner-updated.jpg');
    app()->instance(ImageService::class, $mockUpdate);

    $this->withHeader('Accept', 'application/json')
        ->post("/api/admin/banners/{$bannerId}", [
            '_method' => 'PUT',
            'title' => 'Semana da banana',
            'image' => fakePngUpload('banner-update.png'),
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Semana da banana')
        ->assertJsonPath('data.image_url', 'https://cdn.test/banner-updated.jpg');

    $this->patchJson("/api/admin/banners/{$bannerId}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    Banner::factory()->for($company)->create([
        'title' => 'Ativo cliente',
        'priority' => 10,
        'is_active' => true,
        'period_start' => now()->subHour(),
        'period_end' => now()->addHour(),
    ]);

    Banner::factory()->for($company)->create([
        'title' => 'Expirado',
        'priority' => 20,
        'is_active' => true,
        'period_start' => now()->subDays(2),
        'period_end' => now()->subDay(),
    ]);

    $this->withHeader('X-Company-ID', $company->id)
        ->getJson('/api/client/banners')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Expirado'])
        ->assertJsonFragment(['title' => 'Ativo cliente']);

    $this->deleteJson("/api/admin/banners/{$bannerId}")
        ->assertNoContent();
});
