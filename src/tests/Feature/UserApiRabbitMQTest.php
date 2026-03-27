<?php

namespace Tests\Feature;

use App\Events\UserDataChanged;
use App\Models\User;
use App\Services\RabbitMQService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserApiRabbitMQTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);

        // Disable only auth so route model binding (SubstituteBindings) still runs for PUT /users/{user}
        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Laravel\Passport\Http\Middleware\CheckTokenForAnyScope::class,
        ]);

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')->andReturn(null);
        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    #[Test]
    public function it_dispatches_user_created_event_when_creating_user()
    {
        Event::fake([UserDataChanged::class]);

        $payload = [
            'name' => 'TestUser',
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated();

        Event::assertDispatched(UserDataChanged::class, function ($event) {
            return $event->action === 'created'
                && $event->user->email === 'test@example.com';
        });
    }

    #[Test]
    public function it_dispatches_user_updated_event_when_updating_user()
    {
        $user = User::factory()->create([
            'name' => 'OriginalUser',
        ]);

        Event::fake([UserDataChanged::class]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'UpdatedUser',
        ]);

        $response->assertOk();

        Event::assertDispatched(UserDataChanged::class, function ($event) use ($user) {
            return $event->action === 'updated'
                && $event->user->id === $user->id
                && $event->user->name === 'UpdatedUser';
        });
    }

    #[Test]
    public function it_dispatches_deleted_event_when_deleting_user_via_internal_api()
    {
        config(['services.internal.api_key' => 'test-internal-key']);

        Event::fake([UserDataChanged::class]);

        $user = User::factory()->create();

        $response = $this->deleteJson("/api/internal/users/{$user->id}", [], [
            'X-Internal-Api-Key' => 'test-internal-key',
        ]);

        $response->assertNoContent();

        Event::assertDispatched(UserDataChanged::class, function ($event) use ($user) {
            return $event->action === 'deleted' && $event->user->id === $user->id;
        });
    }

    #[Test]
    public function it_publishes_to_rabbitmq_when_creating_user()
    {
        $publishCalled = false;
        $publishedData = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->with(
                config('rabbitmq.exchanges.users'),
                'user.created',
                Mockery::on(function ($data) use (&$publishCalled, &$publishedData) {
                    $publishCalled = true;
                    $publishedData = $data;
                    return true;
                })
            );

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $payload = [
            'name' => 'RabbitMQ_User',
            'email' => 'rabbitmq@example.com',
            'password' => 'Password123!',
        ];

        $this->postJson('/api/users', $payload);

        $this->assertTrue($publishCalled, 'RabbitMQ publish should be called');
        $this->assertEquals('created', $publishedData['action']);
        $this->assertEquals('RabbitMQ_User', $publishedData['user']['name']);
        $this->assertEquals('rabbitmq@example.com', $publishedData['user']['email']);
    }

    #[Test]
    public function it_publishes_updated_user_data_to_rabbitmq()
    {
        $user = User::factory()->create([
            'name' => 'OldUser',
            'email' => 'old@example.com',
        ]);

        $publishedData = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->with(
                config('rabbitmq.exchanges.users'),
                'user.updated',
                Mockery::on(function ($data) use (&$publishedData) {
                    $publishedData = $data;
                    return true;
                })
            );

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $this->putJson("/api/users/{$user->id}", ['name' => 'NewUser']);

        $this->assertEquals('updated', $publishedData['action']);
        $this->assertEquals('NewUser', $publishedData['user']['name']);
        $this->assertEquals('old@example.com', $publishedData['user']['email']);
    }
}
