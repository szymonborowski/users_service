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
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin_Admin'),
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
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
