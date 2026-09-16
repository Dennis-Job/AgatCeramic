<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Redis;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$host = config('database.redis.queue.host');
$database = (string) config('database.redis.queue.database');
$prefix = config('database.redis.options.prefix');
$safe = app()->environment('testing')
    && getenv('CI') === 'true'
    && config('queue.default') === 'redis'
    && config('queue.connections.redis.connection') === 'queue'
    && in_array($host, ['127.0.0.1', 'localhost', 'queue-test-redis'], true)
    && $database === '14'
    && $prefix === 'agatceramic-queue-test:'
    && ! file_exists(__DIR__.'/../bootstrap/cache/config.php');

if (! $safe) {
    fwrite(STDERR, "Refusing Redis queue integration test: environment is not isolated.\n");
    exit(1);
}

try {
    $response = Redis::connection('queue')->ping();
    if (! in_array($response, [true, 'PONG', '+PONG'], true)) {
        throw new RuntimeException('Unexpected Redis PING response.');
    }
} catch (Throwable) {
    fwrite(STDERR, "Redis queue integration test connection is unavailable.\n");
    exit(1);
}

fwrite(STDOUT, "Verified isolated Redis queue database 14.\n");
