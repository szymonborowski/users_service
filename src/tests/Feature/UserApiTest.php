<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Laravel\Passport\Http\Middleware\CheckForAnyScope::class,
        ]);

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')->andReturn(null);
        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
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

    #[Test]
    public function it_returns_single_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
            ]);
    }

    #[Test]
    public function it_creates_user()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
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

    #[Test]
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

    #[Test]
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
