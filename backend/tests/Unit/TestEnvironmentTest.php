<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestEnvironmentTest extends TestCase
{
    public function test_default_suite_is_isolated_from_docker_services(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('array', config('session.driver'));
    }
}
