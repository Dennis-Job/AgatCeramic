<?php

use App\Models\Cart;
use App\Models\Order;
use App\Services\OrderCreationService;
use App\Services\Retention\OrderDataDestructionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = config('database.connections.pgsql.database');
$host = config('database.connections.pgsql.host');
if (getenv('CI') !== 'true' || ! $app->environment('testing') || DB::getDriverName() !== 'pgsql'
    || ! in_array($database, ['agatceramic_test', 'agatceramic_feature_test'], true)
    || ! in_array($host, ['127.0.0.1', 'localhost'], true)
    || config('database.connections.pgsql.url')) {
    throw new RuntimeException('Readable order worker requires an isolated CI-only PostgreSQL test database.');
}

$operation = $argv[1] ?? '';
/** @var array<string, mixed> $payload */
$payload = json_decode(base64_decode($argv[2] ?? '', true), true, flags: JSON_THROW_ON_ERROR);
DB::selectOne("select set_config('application_name', ?, false)", [$payload['application_name']]);
DB::selectOne("select set_config('statement_timeout', '10000', false)");
Queue::fake();

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    if ($operation === 'checkout') {
        $result = app(OrderCreationService::class)->create(
            Cart::query()->findOrFail($payload['cart_id']),
            $payload['attributes'],
            $payload['idempotency_key'],
        );
        $outcome = ['status' => 'ok', 'order_id' => $result->order->id];
    } elseif (in_array($operation, ['anonymize', 'delete'], true)) {
        $order = Order::query()->findOrFail($payload['order_id']);
        $service = app(OrderDataDestructionService::class);
        if ($operation === 'anonymize') {
            $service->anonymize($order, CarbonImmutable::now());
        } else {
            $service->delete($order);
        }
        $outcome = ['status' => 'ok'];
    } else {
        throw new InvalidArgumentException('Unknown readable order worker operation.');
    }
} catch (Throwable $exception) {
    $outcome = ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR)."\n");
