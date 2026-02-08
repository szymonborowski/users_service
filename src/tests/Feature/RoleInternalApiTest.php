<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\RabbitMQService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleInternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')->andReturn(null);
        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function internalHeaders(): array
    {
        return ['X-Internal-Api-Key' => 'test-api-key'];
    }

    #[Test]
    public function internal_roles_require_api_key(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->getJson('/api/internal/roles');

        $response->assertUnauthorized();
    }

    #[Test]
    public function internal_roles_reject_invalid_api_key(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->getJson('/api/internal/roles', [
            'X-Internal-Api-Key' => 'invalid-key',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function internal_index_returns_roles_ordered_by_level_desc(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->getJson('/api/internal/roles', $this->internalHeaders());

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data));

        $levels = array_column($data, 'level');
        $sorted = $levels;
        rsort($sorted);
        $this->assertEquals($sorted, $levels);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('level', $first);
    }

    #[Test]
    public function internal_get_user_roles_returns_roles_for_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();
        $authorRole = Role::where('name', Role::AUTHOR)->first();
        $user->roles()->attach($authorRole);

        $response = $this->getJson("/api/internal/users/{$user->id}/roles", $this->internalHeaders());

        $response->assertOk()
            ->assertJson([
                'roles' => [Role::AUTHOR],
            ]);
    }

    #[Test]
    public function internal_get_user_roles_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->getJson('/api/internal/users/99999/roles', $this->internalHeaders());

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found']);
    }

    #[Test]
    public function internal_assign_role_adds_role_to_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();

        $response = $this->postJson("/api/internal/users/{$user->id}/roles", [
            'role' => Role::AUTHOR,
        ], $this->internalHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Role assigned',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        $this->assertContains(Role::AUTHOR, $response->json('user.roles'));

        $user->refresh();
        $this->assertTrue($user->roles()->where('name', Role::AUTHOR)->exists());
    }

    #[Test]
    public function internal_assign_role_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->postJson('/api/internal/users/99999/roles', [
            'role' => Role::AUTHOR,
        ], $this->internalHeaders());

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found']);
    }

    #[Test]
    public function internal_assign_role_validation_fails_without_role(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();

        $response = $this->postJson("/api/internal/users/{$user->id}/roles", [], $this->internalHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function internal_assign_role_validation_fails_for_nonexistent_role(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();

        $response = $this->postJson("/api/internal/users/{$user->id}/roles", [
            'role' => 'nonexistent-role',
        ], $this->internalHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function internal_remove_role_removes_role_from_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();
        $readerRole = Role::where('name', Role::READER)->first();
        $user->roles()->attach($readerRole);

        $response = $this->deleteJson("/api/internal/users/{$user->id}/roles/" . Role::READER, [], $this->internalHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Role removed',
                'user' => [
                    'id' => $user->id,
                ],
            ]);
        $this->assertNotContains(Role::READER, $response->json('user.roles'));

        $user->refresh();
        $this->assertFalse($user->roles()->where('name', Role::READER)->exists());
    }

    #[Test]
    public function internal_remove_role_returns_404_for_nonexistent_user(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $response = $this->deleteJson('/api/internal/users/99999/roles/' . Role::READER, [], $this->internalHeaders());

        $response->assertNotFound()
            ->assertJson(['message' => 'User not found']);
    }

    #[Test]
    public function internal_remove_role_returns_404_for_nonexistent_role(): void
    {
        config(['services.internal.api_key' => 'test-api-key']);

        $user = User::factory()->create();

        $response = $this->deleteJson("/api/internal/users/{$user->id}/roles/nonexistent-role", [], $this->internalHeaders());

        $response->assertNotFound()
            ->assertJson(['message' => 'Role not found']);
    }
}
