<?php

namespace App\Listeners;

use App\Events\UserDataChanged;
use App\Services\RabbitMQService;

class PublishUserDataChanged
{
    public function __construct(protected RabbitMQService $rabbitMQ) {}

    public function handle(UserDataChanged $event): void
    {
        $this->rabbitMQ->publish(
            config('rabbitmq.exchanges.users'),
            "user.{$event->action}",
            [
                'action' => $event->action,
                'user'   => [
                    'id'         => $event->user->id,
                    'name'       => $event->user->name,
                    'email'      => $event->user->email,
                    'created_at' => $event->user->created_at?->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}
