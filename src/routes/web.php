<?php

use App\Models\User as UserModel;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    dd([
        'secure' => request()->secure(),
        'scheme' => request()->getScheme(),
        'proto'  => request()->header('x-forwarded-proto'),
    ]);
});
