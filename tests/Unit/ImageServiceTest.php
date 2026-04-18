<?php

use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

it('processa imagem de produto em tres variantes', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD não carregado no CLI atual.');
    }

    Storage::fake('s3');

    $service = app(ImageService::class);
    $urls = $service->processProductImage(fakePngUpload('product.png'), 'company-1', 10);

    expect($urls)
        ->toHaveKeys(['image_thumb_url', 'image_card_url', 'image_full_url']);

    Storage::disk('s3')->assertExists('products/company-1/10/thumb.jpg');
    Storage::disk('s3')->assertExists('products/company-1/10/card.jpg');
    Storage::disk('s3')->assertExists('products/company-1/10/full.jpg');
});

it('faz upload de banner e logo via image service', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD não carregado no CLI atual.');
    }

    Storage::fake('s3');

    $service = app(ImageService::class);
    $bannerUrl = $service->uploadBannerImage(fakePngUpload('banner.png'), 'company-1');
    $logoUrl = $service->uploadLogo(fakePngUpload('logo.png'), 'company-1');

    expect($bannerUrl)->toContain('banners/company-1/main_');
    expect($logoUrl)->toContain('logos/company-1/logo.jpg');

    $bannerPath = collect(Storage::disk('s3')->allFiles('banners/company-1'))->first();
    $logoPath = 'logos/company-1/logo.jpg';

    Storage::disk('s3')->assertExists($bannerPath);
    Storage::disk('s3')->assertExists($logoPath);
});
