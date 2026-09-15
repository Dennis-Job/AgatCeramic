<?php

use App\Services\GuestCartCleanupService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$applicationName = $argv[1] ?? 'guest-cart-cleanup-worker';
DB::selectOne("select set_config('application_name', ?, false)", [$applicationName]);
DB::selectOne("select set_config('statement_timeout', '10000', false)");

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    $outcome = [
        'status' => 'ok',
        'deleted' => app(GuestCartCleanupService::class)->prune(10),
    ];
} catch (Throwable $exception) {
    $outcome = ['status' => 'error', 'message' => $exception->getMessage()];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR)."\n");
