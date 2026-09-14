<?php

use App\Services\Retention\OrderRetentionService;
use App\Services\Retention\RetentionPolicy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
if (! $app instanceof Application) {
    throw new RuntimeException('Laravel application bootstrap failed.');
}
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

config()->set('retention.policy_status', 'accepted');
config()->set('retention.apply_enabled', true);
config()->set('retention.tombstone_key', 'synthetic-postgres-test-key');
config()->set('retention.orders.commercial_disposition', 'retain_commercial');
config()->set('retention.batch_size', 1);
$app->forgetInstance(RetentionPolicy::class);

$results = app(OrderRetentionService::class)->run(false);
$processed = collect($results)->sum(static fn ($result): int => $result->action === 'direct-pii-expiry' ? $result->processed : 0);

fwrite(STDOUT, json_encode(['processed' => $processed], JSON_THROW_ON_ERROR)."\n");
