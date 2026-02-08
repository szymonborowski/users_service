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

    #[Test]
    public function update_by_id_updates_user_and_returns_200(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create(['name' => 'Original Name']);

        $response = $this->putJson("/api/internal/users/{$user->id}", [
            'name' => 'Updated Name',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'Updated Name',
                'email' => $user->email,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function update_by_id_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->putJson('/api/internal/users/99999', [
            'name' => 'X',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found']);
    }

    #[Test]
    public function update_by_id_can_update_email_to_unique_value(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->putJson("/api/internal/users/{$user->id}", [
            'email' => 'new@example.com',
        ], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertOk()
            ->assertJson(['email' => 'new@example.com']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
        ]);
    }

    #[Test]
    public function destroy_by_id_returns_204_and_deletes_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();
        $id = $user->id;

        $response = $this->deleteJson("/api/internal/users/{$id}", [], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    #[Test]
    public function destroy_by_id_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->deleteJson('/api/internal/users/99999', [], [
            'X-Internal-Api-Key' => 'test-api-key',
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found']);
    }
}
