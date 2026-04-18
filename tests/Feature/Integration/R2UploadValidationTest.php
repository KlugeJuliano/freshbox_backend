<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;

function shouldSkipR2Validation(): bool
{
    return ! filter_var(env('RUN_R2_TESTS', false), FILTER_VALIDATE_BOOL)
        || ! extension_loaded('gd')
        || blank(env('AWS_ACCESS_KEY_ID'))
        || blank(env('AWS_SECRET_ACCESS_KEY'))
        || blank(env('AWS_BUCKET'))
        || blank(env('AWS_ENDPOINT'))
        || blank(env('AWS_URL'));
}

it('valida upload real de produto, banner e logo no r2', function () {
    if (shouldSkipR2Validation()) {
        $this->markTestSkipped('Validação real do R2 desabilitada ou ambiente incompleto.');
    }

    [$company] = actingAsAdmin();
    Store::factory()->for($company)->create();
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create();

    $uploadedPaths = [];
    $publicBaseUrl = rtrim((string) config('filesystems.disks.s3.url'), '/');
    $disk = Storage::disk('s3');

    $assertUploadedAsset = function (string $url) use (&$uploadedPaths, $publicBaseUrl, $disk): void {
        expect($url)->toStartWith($publicBaseUrl.'/');

        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        expect($path)->not->toBe('');
        expect($disk->exists($path))->toBeTrue();
        expect($disk->size($path))->toBeGreaterThan(0);
        expect((string) $disk->mimeType($path))->toContain('image/');

        $uploadedPaths[] = $path;
    };

    $productResponse = $this->withHeader('Accept', 'application/json')
        ->post("/api/admin/products/{$product->id}/image", [
            'image' => fakePngUpload('product-r2.png'),
        ])
        ->assertOk();

    foreach (['thumb', 'card', 'full'] as $variant) {
        $assertUploadedAsset((string) $productResponse->json("data.images.{$variant}"));
    }

    $bannerResponse = $this->withHeader('Accept', 'application/json')
        ->post('/api/admin/banners', [
            'title' => 'R2 Real',
            'image' => fakePngUpload('banner-r2.png'),
            'image_mobile' => fakePngUpload('banner-mobile-r2.png'),
            'is_active' => true,
        ])
        ->assertCreated();

    foreach (['data.image_url', 'data.image_mobile_url'] as $jsonPath) {
        $assertUploadedAsset((string) $bannerResponse->json($jsonPath));
    }

    $logoResponse = $this->withHeader('Accept', 'application/json')
        ->post('/api/admin/store/logo', [
            'logo' => fakePngUpload('logo-r2.png'),
        ])
        ->assertOk();

    $assertUploadedAsset((string) $logoResponse->json('logo_url'));

    foreach (array_unique($uploadedPaths) as $path) {
        $disk->delete($path);
    }
});
