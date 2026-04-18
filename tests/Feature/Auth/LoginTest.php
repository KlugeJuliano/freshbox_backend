<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('faz login e retorna token do sanctum', function () {
    $company = Company::factory()->create();

    $user = User::factory()->for($company)->create([
        'email' => 'admin@hortifruti.test',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@hortifruti.test',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token', 'user']);

    expect(PersonalAccessToken::count())->toBe(1);
});
