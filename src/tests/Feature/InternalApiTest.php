<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function internal_api_requires_api_key(): void
    {
        $response = $this->postJson('/api/internal/auth/check', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function internal_api_rejects_invalid_api_key(): void
    {
        $response = $this->postJson('/api/internal/auth/check', [
            'email' => 'test@example.com',
            'password' => 'password',
        ], [
            'X-Internal-Api-Key' => 'invalid-key',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function authorize_returns_user_data_for_valid_credentials(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/internal/auth/check', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'authorized' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    #[Test]
    public function authorize_returns_unauthorized_for_invalid_credentials(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/internal/auth/check', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'authorized' => false,
            ]);
    }

    #[Test]
    public function show_by_id_returns_user_data(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();

        $response = $this->getJson("/api/internal/users/{$user->id}", [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    #[Test]
    public function show_by_id_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->getJson('/api/internal/users/999', [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function internal_create_user_works(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->postJson('/api/internal/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'created_at',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }
}
