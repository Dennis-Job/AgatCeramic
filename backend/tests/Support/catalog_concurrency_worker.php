<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\CategoryManagementService;
use App\Services\ProductImageManagementService;
use App\Services\ProductManagementService;
use App\Services\ProductRelationManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$operation = $argv[1] ?? '';
/** @var array<string, mixed> $payload */
$payload = json_decode(base64_decode($argv[2] ?? '', true), true, flags: JSON_THROW_ON_ERROR);
$applicationName = (string) ($payload['application_name'] ?? 'catalog-concurrency-worker');

DB::selectOne("select set_config('application_name', ?, false)", [$applicationName]);
DB::selectOne("select set_config('statement_timeout', '10000', false)");

$work = match ($operation) {
    'primary-image' => function () use ($payload): void {
        ProductImage::query()->create([
            'product_id' => $payload['product_id'],
            'disk' => 'public',
            'path' => "product-images/{$payload['product_id']}/second.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 16,
            'is_primary' => true,
        ]);
    },
    'upload-image' => (function () use ($payload): Closure {
        $actor = User::query()->findOrFail($payload['actor_id']);
        $product = Product::query()->findOrFail($payload['product_id']);
        $file = UploadedFile::fake()->createWithContent('concurrent.jpg', 'concurrent image');

        return fn () => app(ProductImageManagementService::class)->create(
            $actor,
            $product,
            $file,
            ['is_primary' => (bool) ($payload['is_primary'] ?? false)],
        );
    })(),
    'delete-product' => (function () use ($payload): Closure {
        $actor = User::query()->findOrFail($payload['actor_id']);
        $product = Product::query()->findOrFail($payload['product_id']);

        return fn () => app(ProductManagementService::class)->delete($actor, $product);
    })(),
    'reverse-relation' => (function () use ($payload): Closure {
        $actor = User::query()->findOrFail($payload['actor_id']);
        $product = Product::query()->findOrFail($payload['product_id']);

        return fn () => app(ProductRelationManagementService::class)->replace($actor, $product, [
            ['related_product_id' => $payload['related_product_id'], 'type' => 'recommended'],
        ]);
    })(),
    'category-move' => (function () use ($payload): Closure {
        $actor = User::query()->findOrFail($payload['actor_id']);
        $category = Category::query()->findOrFail($payload['category_id']);

        return fn () => app(CategoryManagementService::class)->update($actor, $category, [
            'parent_id' => $payload['parent_id'],
        ]);
    })(),
    default => throw new InvalidArgumentException("Unknown Catalog concurrency operation: {$operation}"),
};

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

$outcome = ['status' => 'ok'];
try {
    $work();
} catch (Throwable $exception) {
    $outcome = [
        'status' => 'error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR)."\n");
