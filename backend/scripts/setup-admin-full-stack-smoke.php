<?php

declare(strict_types=1);

use App\Enums\AdminUserStatus;
use App\Enums\ContactRequestStatus;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$stateFile = getenv('ADMIN_SMOKE_STATE_FILE');

if (! is_string($stateFile) || $stateFile === '' || dirname($stateFile) !== '/smoke-state') {
    fwrite(STDERR, "Refusing to create Admin smoke fixtures outside the ephemeral state volume.\n");
    exit(1);
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = config("database.connections.{$connection}.database");
$host = config("database.connections.{$connection}.host");
$url = config("database.connections.{$connection}.url");

if (
    ! app()->environment('testing')
    || getenv('CI') !== 'true'
    || $connection !== 'pgsql'
    || $database !== 'agatceramic_admin_smoke_test'
    || $host !== 'admin-smoke-postgres'
    || ! empty($url)
    || file_exists(__DIR__.'/../bootstrap/cache/config.php')
) {
    fwrite(STDERR, "Refusing to create Admin smoke fixtures outside the isolated test environment.\n");
    exit(1);
}

umask(0077);
$runId = bin2hex(random_bytes(8));
$fullAccessPassword = bin2hex(random_bytes(18)).'Aa1!';
$viewOnlyPassword = bin2hex(random_bytes(18)).'Bb2!';

$state = DB::transaction(function () use ($runId, $fullAccessPassword, $viewOnlyPassword): array {
    $fullAccessUser = User::query()->create([
        'name' => "smoke-admin-{$runId}",
        'email' => "smoke-admin-{$runId}@example.test",
        'password' => $fullAccessPassword,
        'status' => AdminUserStatus::Active,
    ]);
    $fullAccessUser->roles()->attach(Role::query()->where('slug', 'administrator')->sole());

    $viewOnlyRole = Role::query()->create([
        'name' => "smoke-viewer-{$runId}",
        'slug' => "smoke-viewer-{$runId}",
        'description' => null,
        'is_system' => false,
    ]);
    $viewOnlyRole->permissions()->attach(Permission::query()->where('code', 'orders.view')->sole());

    $viewOnlyUser = User::query()->create([
        'name' => "smoke-viewer-{$runId}",
        'email' => "smoke-viewer-{$runId}@example.test",
        'password' => $viewOnlyPassword,
        'status' => AdminUserStatus::Active,
    ]);
    $viewOnlyUser->roles()->attach($viewOnlyRole);

    $brand = Brand::query()->create([
        'name' => "smoke-brand-{$runId}",
        'slug' => "smoke-brand-{$runId}",
        'description' => null,
        'country_code' => null,
        'is_active' => true,
    ]);

    $order = Order::query()->create([
        'order_number' => 'AC-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),
        'customer_name' => "smoke-customer-{$runId}",
        'customer_phone' => '+7'.random_int(1000000000, 9999999999),
        'customer_email' => "smoke-customer-{$runId}@example.test",
        'delivery_address' => "smoke-address-{$runId}",
        'customer_comment' => null,
        'status' => 'new',
        'payment_status' => PaymentStatus::NotPaid,
        'total_amount' => '1000.00',
    ]);

    $contact = ContactRequest::query()->create([
        'type' => 'callback',
        'name' => "smoke-contact-{$runId}",
        'phone' => '+7'.random_int(1000000000, 9999999999),
        'email' => null,
        'message' => null,
        'source' => 'admin-full-stack-smoke',
        'status' => ContactRequestStatus::New,
        'assignee_id' => null,
        'assigned_at' => null,
        'completed_at' => null,
    ]);

    return [
        'full_access' => [
            'email' => $fullAccessUser->email,
            'password' => $fullAccessPassword,
        ],
        'view_only' => [
            'email' => $viewOnlyUser->email,
            'password' => $viewOnlyPassword,
        ],
        'brand' => ['id' => $brand->id, 'name' => $brand->name],
        'order' => ['id' => $order->id, 'number' => $order->order_number],
        'contact' => ['id' => $contact->id],
    ];
});

if (! is_dir(dirname($stateFile)) && ! mkdir(dirname($stateFile), 0700, true) && ! is_dir(dirname($stateFile))) {
    fwrite(STDERR, "Unable to create the Admin smoke state directory.\n");
    exit(1);
}

$temporaryFile = $stateFile.'.tmp';
$encodedState = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

if (file_put_contents($temporaryFile, $encodedState, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the Admin smoke state.\n");
    exit(1);
}

chmod($temporaryFile, 0600);
rename($temporaryFile, $stateFile);
fwrite(STDOUT, "Admin full-stack smoke fixtures are ready.\n");
