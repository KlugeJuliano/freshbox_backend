<?php

use App\Http\Resources\BannerResource;
use App\Http\Resources\StoreResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Carbon\Carbon;

it('avalia comportamentos e relacionamentos principais dos models', function () {
    $company = Company::factory()->create();
    $store = Store::factory()->for($company)->create([
        'business_hours' => [
            'mon' => ['open' => '08:00', 'close' => '18:00'],
        ],
    ]);
    $category = Category::factory()->for($company)->create();
    $product = Product::factory()->for($company)->for($category)->create([
        'price' => 10,
        'promo_price' => 7.5,
        'promo_ends_at' => now()->addWeek(),
        'is_featured' => true,
    ]);
    $banner = Banner::factory()->for($company)->create();
    $order = Order::factory()->for($company)->for($store)->create();
    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_unit' => $product->unit,
        'unit_price' => 10,
        'quantity' => 1,
        'subtotal' => 10,
    ]);
    $image = ProductImage::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'image_url' => 'https://cdn.test/1.jpg',
        'label' => 'principal',
        'sort_order' => 1,
    ]);

    Carbon::setTestNow(now()->next('Monday')->setTime(10, 0));

    expect($store->isOpenNow())->toBeTrue()
        ->and($company->stores()->first()->is($store))->toBeTrue()
        ->and($company->categories()->first()->is($category))->toBeTrue()
        ->and($company->products()->first()->is($product))->toBeTrue()
        ->and($company->banners()->first()->is($banner))->toBeTrue()
        ->and($company->orders()->first()->is($order))->toBeTrue()
        ->and($product->effective_price)->toBe('7.50')
        ->and($product->is_on_promo)->toBeTrue()
        ->and(Product::active()->featured()->count())->toBeGreaterThan(0)
        ->and(Product::onPromo()->count())->toBeGreaterThan(0)
        ->and($category->products()->first()->is($product))->toBeTrue()
        ->and($product->images()->first()->is($image))->toBeTrue()
        ->and($item->order()->first()->is($order))->toBeTrue()
        ->and($item->product()->first()->is($product))->toBeTrue();

    Carbon::setTestNow();
});

it('filtra banners ativos e serializa store e banner resources', function () {
    $company = Company::factory()->create();
    $store = Store::factory()->for($company)->create([
        'address_city' => 'Curitiba',
        'address_lat' => -25.4284,
        'address_lng' => -49.2733,
    ]);

    $activeBanner = Banner::factory()->for($company)->create([
        'period_start' => now()->subHour(),
        'period_end' => now()->addHour(),
        'is_active' => true,
    ]);

    Banner::factory()->for($company)->create([
        'period_start' => now()->subDays(2),
        'period_end' => now()->subDay(),
        'is_active' => true,
    ]);

    $storeArray = (new StoreResource($store))->toArray(request());
    $bannerArray = (new BannerResource($activeBanner))->toArray(request());

    expect(Banner::active()->pluck('id')->all())->toContain($activeBanner->id)
        ->and($storeArray['address']['city'])->toBe('Curitiba')
        ->and($storeArray['address']['lat'])->toBeFloat()
        ->and($bannerArray['id'])->toBe($activeBanner->id)
        ->and($bannerArray['period_start'])->toBeString();
});
