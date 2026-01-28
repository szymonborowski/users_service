<?php

namespace App\Events;

use App\Models\User;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Events\Dispatchable;

class UserDataChanged
{
    use Dispatchable;

    public function __construct(
        public User $user,
        public string $action
    ) {
        $this->publishToRabbitMQ();
    }

    private function publishToRabbitMQ(): void
    {
        $rabbitMQ = app(RabbitMQService::class);

        $rabbitMQ->publish(
            config('rabbitmq.exchanges.users'),
            "user.{$this->action}",
            [
                'action' => $this->action,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'created_at' => $this->user->created_at?->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}
