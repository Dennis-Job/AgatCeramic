<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        if (file_exists(__DIR__.'/../bootstrap/cache/config.php')) {
            throw new RuntimeException('Remove the cached application configuration before running tests.');
        }

        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        $url = $app['config']->get("database.connections.{$connection}.url");
        $inMemory = $connection === 'sqlite' && $database === ':memory:';
        $isolatedPostgres = $connection === 'pgsql'
            && $database === 'agatceramic_test'
            && getenv('CI') === 'true';

        if (! $app->environment('testing') || $url || (! $inMemory && ! $isolatedPostgres)) {
            throw new RuntimeException('Unsafe test database: use SQLite :memory: or the CI-only agatceramic_test database.');
        }

        return $app;
    }
}
