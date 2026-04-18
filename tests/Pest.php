<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

function fakePngUpload(string $name = 'image.png'): UploadedFile
{
    if (extension_loaded('gd')) {
        return UploadedFile::fake()->image($name, 32, 32);
    }

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9sXl16sAAAAASUVORK5CYII='
    );

    return UploadedFile::fake()->createWithContent($name, $png);
}

function actingAsAdmin(?\App\Models\Company $company = null): array
{
    $company ??= \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->for($company)->create();

    \Laravel\Sanctum\Sanctum::actingAs($user, ['admin']);

    return [$company, $user];
}
