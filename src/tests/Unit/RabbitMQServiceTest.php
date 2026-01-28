<?php

namespace Tests\Unit;

use App\Services\RabbitMQService;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMQServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_publishes_message_to_rabbitmq_with_correct_format()
    {
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnection = Mockery::mock(AMQPStreamConnection::class);

        $mockConnection->shouldReceive('isConnected')->andReturn(false);
        $mockConnection->shouldReceive('channel')->once()->andReturn($mockChannel);

        $mockChannel->shouldReceive('exchange_declare')
            ->once()
            ->with('test-exchange', 'topic', false, true, false);

        $capturedMessage = null;
        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->withArgs(function ($message, $exchange, $routingKey) use (&$capturedMessage) {
                $capturedMessage = $message;
                return $exchange === 'test-exchange' && $routingKey === 'test.routing.key';
            });

        $mockChannel->shouldReceive('close')->once();

        $service = new TestableRabbitMQService($mockConnection);
        $service->publish('test-exchange', 'test.routing.key', ['key' => 'value']);

        $this->assertInstanceOf(AMQPMessage::class, $capturedMessage);
        $this->assertEquals(['key' => 'value'], json_decode($capturedMessage->getBody(), true));
        $this->assertEquals('application/json', $capturedMessage->get('content_type'));
        $this->assertEquals(AMQPMessage::DELIVERY_MODE_PERSISTENT, $capturedMessage->get('delivery_mode'));
    }

    #[Test]
    public function it_creates_persistent_message_with_json_content_type()
    {
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnection = Mockery::mock(AMQPStreamConnection::class);

        $mockConnection->shouldReceive('isConnected')->andReturn(false);
        $mockConnection->shouldReceive('channel')->once()->andReturn($mockChannel);

        $mockChannel->shouldReceive('exchange_declare')->once();
        $mockChannel->shouldReceive('close')->once();

        $capturedMessage = null;
        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->withArgs(function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
                return true;
            });

        $service = new TestableRabbitMQService($mockConnection);
        $service->publish('exchange', 'key', ['data' => 'test']);

        $this->assertEquals('application/json', $capturedMessage->get('content_type'));
        $this->assertEquals(AMQPMessage::DELIVERY_MODE_PERSISTENT, $capturedMessage->get('delivery_mode'));
    }

    #[Test]
    public function it_declares_topic_exchange_before_publishing()
    {
        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockConnection = Mockery::mock(AMQPStreamConnection::class);

        $mockConnection->shouldReceive('isConnected')->andReturn(false);
        $mockConnection->shouldReceive('channel')->once()->andReturn($mockChannel);

        $callOrder = [];

        $mockChannel->shouldReceive('exchange_declare')
            ->once()
            ->with('users', 'topic', false, true, false)
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'exchange_declare';
            });

        $mockChannel->shouldReceive('basic_publish')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'basic_publish';
            });

        $mockChannel->shouldReceive('close')->once();

        $service = new TestableRabbitMQService($mockConnection);
        $service->publish('users', 'user.created', ['id' => 1]);

        $this->assertEquals(['exchange_declare', 'basic_publish'], $callOrder);
    }
}

class TestableRabbitMQService extends RabbitMQService
{
    private AMQPStreamConnection $mockConnection;

    public function __construct(AMQPStreamConnection $connection)
    {
        $this->mockConnection = $connection;
    }

    protected function getConnection(): AMQPStreamConnection
    {
        return $this->mockConnection;
    }
}
