<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'users.read'  => 'Read users data',
            'users.write' => 'Create and modify users',
            'users.auth'  => 'Authenticate users',
        ]);

        Passport::tokensExpireIn(now()->addMinutes(15));
    }
}
