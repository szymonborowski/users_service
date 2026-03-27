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
