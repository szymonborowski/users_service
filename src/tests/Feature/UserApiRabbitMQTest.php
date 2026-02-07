<?php

namespace Tests\Feature;

use App\Events\UserDataChanged;
use App\Models\User;
use App\Services\RabbitMQService;
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

        $this->withoutMiddleware();

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
    public function it_dispatches_user_created_event_when_creating_user()
    {
        Event::fake([UserDataChanged::class]);

        $payload = [
            'name' => 'Test User',
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
            'name' => 'Original Name',
        ]);

        Event::fake([UserDataChanged::class]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();

        Event::assertDispatched(UserDataChanged::class, function ($event) use ($user) {
            return $event->action === 'updated'
                && $event->user->id === $user->id
                && $event->user->name === 'Updated Name';
        });
    }

    #[Test]
    public function it_does_not_dispatch_event_when_deleting_user()
    {
        Event::fake([UserDataChanged::class]);

        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertNoContent();

        Event::assertNotDispatched(UserDataChanged::class);
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
            'name' => 'RabbitMQ Test User',
            'email' => 'rabbitmq@example.com',
            'password' => 'Password123!',
        ];

        $this->postJson('/api/users', $payload);

        $this->assertTrue($publishCalled, 'RabbitMQ publish should be called');
        $this->assertEquals('created', $publishedData['action']);
        $this->assertEquals('RabbitMQ Test User', $publishedData['user']['name']);
        $this->assertEquals('rabbitmq@example.com', $publishedData['user']['email']);
    }

    #[Test]
    public function it_publishes_updated_user_data_to_rabbitmq()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
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

        $this->putJson("/api/users/{$user->id}", ['name' => 'New Name']);

        $this->assertEquals('updated', $publishedData['action']);
        $this->assertEquals('New Name', $publishedData['user']['name']);
        $this->assertEquals('old@example.com', $publishedData['user']['email']);
    }
}
