<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedisConfigurationTest extends TestCase
{
    public function test_redis_is_configured_for_cache_with_a_separate_database(): void
    {
        $this->assertSame('phpredis', config('database.redis.client'));
        $this->assertSame('0', config('database.redis.default.database'));
        $this->assertSame('1', config('database.redis.cache.database'));
        $this->assertSame('cache', config('cache.stores.redis.connection'));
    }

    public function test_redis_is_the_default_queue_with_a_dedicated_database(): void
    {
        $this->assertSame('queue', config('queue.connections.redis.connection'));
        $this->assertSame('2', config('database.redis.queue.database'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
    }
}
