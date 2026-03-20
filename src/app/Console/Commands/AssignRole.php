<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignRole extends Command
{
    protected $signature = 'users:assign-role
                            {--email= : The email of the user}
                            {--role= : The role to assign (' . Role::ADMIN . '|' . Role::MODERATOR . '|' . Role::AUTHOR . '|' . Role::READER . ')}';

    protected $description = 'Assign a role to an existing user';

    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Email');
        $role = $this->option('role') ?? $this->choice('Role', [
            Role::ADMIN,
            Role::MODERATOR,
            Role::AUTHOR,
            Role::READER,
        ]);

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return self::FAILURE;
        }

        if (!in_array($role, [Role::ADMIN, Role::MODERATOR, Role::AUTHOR, Role::READER], true)) {
            $this->error("Invalid role: {$role}");
            return self::FAILURE;
        }

        $user->assignRole($role);

        $this->info("Role '{$role}' assigned to {$user->name} ({$user->email})");

        return self::SUCCESS;
    }
}
