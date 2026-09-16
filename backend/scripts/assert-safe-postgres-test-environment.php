<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$allowedTargets = [
    'agatceramic_test' => ['127.0.0.1', 'localhost'],
    'agatceramic_feature_test' => ['127.0.0.1', 'localhost'],
    'agatceramic_queue_test' => ['127.0.0.1', 'localhost', 'queue-test-postgres'],
    'agatceramic_admin_smoke_test' => ['admin-smoke-postgres'],
];
$expectedDatabase = $argv[1] ?? null;

if (! is_string($expectedDatabase) || ! isset($allowedTargets[$expectedDatabase])) {
    fwrite(STDERR, "Expected an allowlisted PostgreSQL test database name.\n");
    exit(1);
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = config("database.connections.{$connection}.database");
$host = config("database.connections.{$connection}.host");
$url = config("database.connections.{$connection}.url");
$safe = app()->environment('testing')
    && getenv('CI') === 'true'
    && $connection === 'pgsql'
    && $database === $expectedDatabase
    && in_array($host, $allowedTargets[$expectedDatabase], true)
    && empty($url)
    && ! file_exists(__DIR__.'/../bootstrap/cache/config.php');

if (! $safe) {
    fwrite(STDERR, "Refusing destructive PostgreSQL test operation: environment is not isolated.\n");
    exit(1);
}

fwrite(STDOUT, "Verified isolated PostgreSQL test database: {$expectedDatabase}.\n");
