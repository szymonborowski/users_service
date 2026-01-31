<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => Role::ADMIN,
                'description' => 'Full system access',
                'level' => 100,
            ],
            [
                'name' => Role::MODERATOR,
                'description' => 'Can approve posts and comments',
                'level' => 50,
            ],
            [
                'name' => Role::AUTHOR,
                'description' => 'Can create and manage own posts',
                'level' => 20,
            ],
            [
                'name' => Role::READER,
                'description' => 'Can read and comment on posts',
                'level' => 10,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
