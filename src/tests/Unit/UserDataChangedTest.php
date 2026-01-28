<?php

namespace Tests\Unit;

use App\Events\UserDataChanged;
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

    #[Test]
    public function it_publishes_user_created_event_with_correct_data()
    {
        $publishedData = null;
        $publishedExchange = null;
        $publishedRoutingKey = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function ($exchange, $routingKey, $data) use (&$publishedExchange, &$publishedRoutingKey, &$publishedData) {
                $publishedExchange = $exchange;
                $publishedRoutingKey = $routingKey;
                $publishedData = $data;
            });

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $user->id = 1;
        $user->created_at = Carbon::create(2026, 1, 15, 10, 30, 0);

        UserDataChanged::dispatch($user, 'created');

        $this->assertEquals(config('rabbitmq.exchanges.users'), $publishedExchange);
        $this->assertEquals('user.created', $publishedRoutingKey);
        $this->assertEquals('created', $publishedData['action']);
        $this->assertEquals(1, $publishedData['user']['id']);
        $this->assertEquals('John Doe', $publishedData['user']['name']);
        $this->assertEquals('john@example.com', $publishedData['user']['email']);
        $this->assertArrayHasKey('created_at', $publishedData['user']);
        $this->assertArrayHasKey('timestamp', $publishedData);
    }

    #[Test]
    public function it_publishes_user_updated_event_with_correct_routing_key()
    {
        $publishedRoutingKey = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function ($exchange, $routingKey, $data) use (&$publishedRoutingKey) {
                $publishedRoutingKey = $routingKey;
            });

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id = 1;
        $user->created_at = now();

        UserDataChanged::dispatch($user, 'updated');

        $this->assertEquals('user.updated', $publishedRoutingKey);
    }

    #[Test]
    public function it_includes_only_public_user_data()
    {
        $publishedData = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function ($exchange, $routingKey, $data) use (&$publishedData) {
                $publishedData = $data;
            });

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('secret-password'),
        ]);
        $user->id = 1;
        $user->created_at = now();
        $user->remember_token = 'some-token';

        UserDataChanged::dispatch($user, 'created');

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

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function ($exchange, $routingKey, $data) use (&$publishedData) {
                $publishedData = $data;
            });

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        $fixedDate = Carbon::create(2026, 1, 15, 10, 30, 0);
        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id = 1;
        $user->created_at = $fixedDate;

        UserDataChanged::dispatch($user, 'created');

        $this->assertStringContainsString('2026-01-15', $publishedData['user']['created_at']);
    }

    #[Test]
    public function it_includes_timestamp_of_event()
    {
        $publishedData = null;

        $mockRabbitMQ = Mockery::mock(RabbitMQService::class);
        $mockRabbitMQ->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function ($exchange, $routingKey, $data) use (&$publishedData) {
                $publishedData = $data;
            });

        $this->app->instance(RabbitMQService::class, $mockRabbitMQ);

        Carbon::setTestNow(Carbon::create(2026, 1, 28, 12, 0, 0));

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $user->id = 1;
        $user->created_at = now();

        UserDataChanged::dispatch($user, 'created');

        $this->assertArrayHasKey('timestamp', $publishedData);
        $this->assertStringContainsString('2026-01-28', $publishedData['timestamp']);

        Carbon::setTestNow();
    }
}
