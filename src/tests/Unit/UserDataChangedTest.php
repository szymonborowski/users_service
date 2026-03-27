<?php

namespace Tests\Unit;

use App\Events\UserDataChanged;
use App\Listeners\PublishUserDataChanged;
use App\Models\User;
use App\Services\RabbitMQService;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDataChangedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockRabbitMQ(?callable $callback = null): RabbitMQService
    {
        $mock = Mockery::mock(RabbitMQService::class);
        $expectation = $mock->shouldReceive('publish')->once();

        if ($callback) {
            $expectation->andReturnUsing($callback);
        }

        return $mock;
    }

    private function dispatch(User $user, string $action, RabbitMQService $rabbitMQ): void
    {
        $listener = new PublishUserDataChanged($rabbitMQ);
        $listener->handle(new UserDataChanged($user, $action));
    }

    #[Test]
    public function it_publishes_user_created_event_with_correct_data()
    {
        $publishedData = $publishedExchange = $publishedRoutingKey = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (
            &$publishedExchange, &$publishedRoutingKey, &$publishedData
        ) {
            $publishedExchange   = $exchange;
            $publishedRoutingKey = $routingKey;
            $publishedData       = $data;
        });

        $user = new User(['name' => 'John_Doe', 'email' => 'john@example.com']);
        $user->id         = 1;
        $user->created_at = Carbon::create(2026, 1, 15, 10, 30, 0);

        $this->dispatch($user, 'created', $rabbitMQ);

        $this->assertEquals(config('rabbitmq.exchanges.users'), $publishedExchange);
        $this->assertEquals('user.created', $publishedRoutingKey);
        $this->assertEquals('created', $publishedData['action']);
        $this->assertEquals(1, $publishedData['user']['id']);
        $this->assertEquals('John_Doe', $publishedData['user']['name']);
        $this->assertEquals('john@example.com', $publishedData['user']['email']);
        $this->assertArrayHasKey('created_at', $publishedData['user']);
        $this->assertArrayHasKey('timestamp', $publishedData);
    }

    #[Test]
    public function it_publishes_user_updated_event_with_correct_routing_key()
    {
        $publishedRoutingKey = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (&$publishedRoutingKey) {
            $publishedRoutingKey = $routingKey;
        });

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id         = 1;
        $user->created_at = now();

        $this->dispatch($user, 'updated', $rabbitMQ);

        $this->assertEquals('user.updated', $publishedRoutingKey);
    }

    #[Test]
    public function it_publishes_user_deleted_event_with_correct_routing_key()
    {
        $publishedRoutingKey = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (&$publishedRoutingKey) {
            $publishedRoutingKey = $routingKey;
        });

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id         = 1;
        $user->created_at = now();

        $this->dispatch($user, 'deleted', $rabbitMQ);

        $this->assertEquals('user.deleted', $publishedRoutingKey);
    }

    #[Test]
    public function it_includes_only_public_user_data()
    {
        $publishedData = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (&$publishedData) {
            $publishedData = $data;
        });

        $user                  = new User(['name' => 'Test_User', 'email' => 'test@example.com', 'password' => bcrypt('secret')]);
        $user->id              = 1;
        $user->created_at      = now();
        $user->remember_token  = 'some-token';

        $this->dispatch($user, 'created', $rabbitMQ);

        $this->assertArrayHasKey('id', $publishedData['user']);
        $this->assertArrayHasKey('name', $publishedData['user']);
        $this->assertArrayHasKey('email', $publishedData['user']);
        $this->assertArrayHasKey('created_at', $publishedData['user']);
        $this->assertArrayNotHasKey('password', $publishedData['user']);
        $this->assertArrayNotHasKey('remember_token', $publishedData['user']);
    }

    #[Test]
    public function it_formats_created_at_as_iso_string()
    {
        $publishedData = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (&$publishedData) {
            $publishedData = $data;
        });

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id         = 1;
        $user->created_at = Carbon::create(2026, 1, 15, 10, 30, 0);

        $this->dispatch($user, 'created', $rabbitMQ);

        $this->assertStringContainsString('2026-01-15', $publishedData['user']['created_at']);
    }

    #[Test]
    public function it_includes_timestamp_of_event()
    {
        $publishedData = null;

        $rabbitMQ = $this->mockRabbitMQ(function ($exchange, $routingKey, $data) use (&$publishedData) {
            $publishedData = $data;
        });

        Carbon::setTestNow(Carbon::create(2026, 1, 28, 12, 0, 0));

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id         = 1;
        $user->created_at = now();

        $this->dispatch($user, 'created', $rabbitMQ);

        $this->assertArrayHasKey('timestamp', $publishedData);
        $this->assertStringContainsString('2026-01-28', $publishedData['timestamp']);

        Carbon::setTestNow();
    }
}
