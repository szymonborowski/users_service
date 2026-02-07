<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PhpAmqpLib\Connection\AMQPStreamConnection;

// Kubernetes liveness probe - process is alive
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

// Kubernetes readiness probe - DB and RabbitMQ are reachable
Route::get('/ready', function () {
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = $e->getMessage();
        return response()->json(['status' => 'not ready', 'checks' => $checks], 503);
    }

    try {
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost')
        );
        $connection->close();
        $checks['rabbitmq'] = 'ok';
    } catch (\Throwable $e) {
        $checks['rabbitmq'] = $e->getMessage();
        return response()->json(['status' => 'not ready', 'checks' => $checks], 503);
    }

    return response()->json(['status' => 'ready', 'checks' => $checks], 200);
});

Route::get('/', function () {
    return response()->json(['status' => 'ok']);
});
