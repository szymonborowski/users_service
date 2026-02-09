<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Seed the application's database with initial users.
     */
    public function run(): void
    {
        // Create frontend user (regular user with READER role)
        $frontendUser = User::updateOrCreate(
            ['email' => 'szymon.borowski@example.com'],
            [
                'name' => 'Szymon Borowski',
                'email' => 'szymon.borowski@example.com',
                'password' => Hash::make('Admin,123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign READER role to frontend user
        $readerRole = Role::where('name', Role::READER)->first();
        if ($readerRole && !$frontendUser->hasRole(Role::READER)) {
            $frontendUser->assignRole(Role::READER);
        }

        $this->command->info("✓ Frontend user created: {$frontendUser->email}");

        // Create admin user
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin,123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign ADMIN role to admin user
        $adminRole = Role::where('name', Role::ADMIN)->first();
        if ($adminRole && !$adminUser->hasRole(Role::ADMIN)) {
            $adminUser->assignRole(Role::ADMIN);
        }

        $this->command->info("✓ Admin user created: {$adminUser->email}");
    }
}
