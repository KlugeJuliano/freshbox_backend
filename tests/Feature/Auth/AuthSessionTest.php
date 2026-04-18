<?php

use App\Models\Company;
use App\Models\User;

it('retorna usuario autenticado e faz logout', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create([
        'email' => 'me@hortifruti.test',
        'password' => 'password',
    ]);

    $token = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('company.id', $company->id);

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout realizado.');
});

it('rejeita credenciais invalidas', function () {
    $company = Company::factory()->create();
    User::factory()->for($company)->create([
        'email' => 'admin@hortifruti.test',
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@hortifruti.test',
        'password' => 'errada',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});
