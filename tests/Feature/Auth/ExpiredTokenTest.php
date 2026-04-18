<?php

use App\Models\Company;
use App\Models\User;

it('retorna 401 para token expirado', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $token = $user->createToken('expired', ['admin'], now()->subMinute())->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});
