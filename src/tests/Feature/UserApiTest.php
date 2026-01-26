<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_paginated_users_list()
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email'],
                ],
            ]);
    }

    /** @test */
    public function it_returns_single_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    /** @test */
    public function it_creates_user()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ];

        $response = $this->postJson('/api/users', $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'email' => 'test@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function it_updates_user()
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/users/{$user->id}", $payload);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /** @test */
    public function it_deletes_user()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
