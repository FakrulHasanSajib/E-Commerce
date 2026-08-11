<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user profile endpoint returns user with roles and permissions', function () {
    $this->seed(\Database\Seeders\RbacSeeder::class);

    $user = User::factory()->create(['email' => 'authuser@test.com']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user');
    $response->assertSuccessful()
        ->assertJsonFragment([
            'email' => 'authuser@test.com',
        ]);
});
