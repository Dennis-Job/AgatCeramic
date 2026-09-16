#!/usr/bin/env sh
set -eu

php scripts/assert-safe-postgres-test-environment.php agatceramic_queue_test
php scripts/assert-safe-redis-queue-test-environment.php
php artisan migrate:fresh --force --no-interaction
vendor/bin/phpunit --configuration=phpunit.redis-queue.xml tests/Integration/RedisQueueDeliveryTest.php
