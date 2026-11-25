<?php

declare(strict_types=1);

use App\Queries\ProductQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$mode = $argv[1] ?? null;
$output = $argv[2] ?? null;
if ($mode !== 'seed' && ($mode !== 'measure' || $output === null)) {
    fwrite(STDERR, "Usage: php scripts/benchmark-product-query.php seed | measure <plans.json>\n");
    exit(1);
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'pgsql' || DB::getDatabaseName() !== 'agatceramic_feature_test'
    || ! in_array(DB::getConfig('host'), ['127.0.0.1', 'localhost'], true) || DB::getConfig('url') || getenv('CI') !== 'true') {
    fwrite(STDERR, "Expected the isolated PostgreSQL feature test database.\n");
    exit(1);
}

try {
    if ($mode === 'seed') {
        if (DB::table('products')->exists() || DB::table('categories')->exists() || DB::table('brands')->exists()) {
            throw new RuntimeException('The synthetic fixture requires an empty catalog.');
        }

        DB::transaction(fn () => DB::unprepared(<<<'SQL'
            INSERT INTO categories (name, slug, is_parent, is_active)
            SELECT 'Категория ' || n, 'cat-' || n, false, true FROM generate_series(1, 40) n;
            INSERT INTO brands (name, slug, is_active)
            SELECT 'Фабрика ' || n, 'brand-' || n, true FROM generate_series(1, 80) n;
            INSERT INTO product_collections (brand_id, name, slug, is_active)
            SELECT b.id, CASE WHEN b.slug = 'brand-5' AND c = 5 THEN 'Флоренция' ELSE 'Коллекция ' || c || ' ' || b.name END,
              'collection-' || c, c % 5 <> 0
            FROM brands b CROSS JOIN generate_series(1, 40) c ORDER BY b.id, c;
            INSERT INTO products (category_id, brand_id, collection_id, name, slug, sku, article_number, barcode,
              unit, price, stock_quantity, is_active, is_on_sale, created_at, updated_at)
            SELECT cat.id, b.id, co.id,
              (ARRAY['Плитка мрамор белая', 'Мозаика синяя', 'Керамогранит серый', 'Затирка бежевая', 'Плитка гранит графит'])[1 + (n % 5)]
                || CASE WHEN n % 3911 = 0 THEN ' Ониксолит' ELSE '' END || ' ' || (n % 120),
              'catalog-' || md5(n::text), lpad(n::text, 7, '0'),
              CASE WHEN n % 7 <> 0 THEN 'ART-' || left(md5(n::text), 20) END,
              CASE WHEN n % 11 <> 0 THEN lpad((7020000000000 + n * 13)::text, 13, '0') END,
              'piece', CASE WHEN n % 29 <> 0 THEN 120 + (n * 73 % 200000) / 100.0 END,
              CASE WHEN n % 13 <> 0 THEN CASE WHEN n % 5 = 0 THEN 0 ELSE n % 157 END END,
              n % 17 <> 0, n % 11 = 0, timestamp '2026-01-01' + (n / 200) * interval '1 hour',
              timestamp '2026-02-01' + (n / 200) * interval '1 hour'
            FROM generate_series(1, 200000) n
            JOIN categories cat ON cat.slug = 'cat-' || (CASE WHEN n % 4 = 0 THEN 1 ELSE 1 + (n * 19 + n / 401) % 40 END)
            LEFT JOIN brands b ON b.slug = 'brand-' || (1 + (n * 11 + n / 89) % 80) AND n % 19 <> 0
            LEFT JOIN product_collections co ON co.brand_id = b.id AND co.slug = 'collection-' || (1 + (n * 7 + n / 241) % 40) AND n % 3 <> 0
            ORDER BY n;
            SQL));
        DB::unprepared('VACUUM (ANALYZE) products');
        DB::unprepared('VACUUM (ANALYZE) product_collections');
        DB::unprepared('ANALYZE brands');
        DB::unprepared('ANALYZE categories');
        fwrite(STDOUT, "Loaded 200,000 synthetic products.\n");
        exit;
    }

    $category = DB::table('categories')->where('slug', 'cat-1')->value('id');
    $brand = DB::table('brands')->where('slug', 'brand-5')->value('id');
    if ($category === null || $brand === null || DB::table('products')->count() !== 200000) {
        throw new RuntimeException('Load the synthetic catalog with the seed command before measuring.');
    }

    $query = new ProductQuery;
    $scenarios = [
        'unfiltered' => [],
        'rare_cyrillic' => ['search' => 'Ониксолит'],
        'collection_only' => ['search' => 'Флоренция'],
        'short' => ['search' => 'ик'],
        'common' => ['search' => 'плитка'],
        'wildcard' => ['search' => '%'],
        'identifier' => ['search' => 'ART-8b0'],
        'missing' => ['search' => 'АбсолютноОтсутствующийТовар'],
        'filtered' => [
            'search' => 'плитка',
            'category_id' => $category,
            'brand_id' => $brand,
            'has_stock' => true,
            'is_active' => false,
            'is_on_sale' => true,
            'price_from' => 400,
            'price_to' => 1400,
            'sort' => 'name',
            'direction' => 'asc',
        ],
    ];
    $results = [];

    foreach ($scenarios as $scenario => $filters) {
        $operations = [
            'counts' => fn () => $query->filterCounts($filters),
            'listing' => function () use ($query, $filters): array {
                $page = $query->filtered($filters)->toBase()->paginate(25, page: 1);

                return ['total' => $page->total(), 'ids' => $page->getCollection()->pluck('id')->all()];
            },
        ];
        foreach ($operations as $operation => $run) {
            $samples = [];
            DB::enableQueryLog();
            for ($sample = 0; $sample <= 7; $sample++) {
                DB::flushQueryLog();
                $result = $run();
                $statements = DB::getQueryLog();
                if ($sample > 0) {
                    $samples[] = array_sum(array_column($statements, 'time'));
                }
            }
            DB::disableQueryLog();
            DB::flushQueryLog();

            sort($samples);
            $plans = [];
            foreach ($statements as $statement) {
                $plan = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$statement['query'], $statement['bindings']);
                $plans[] = [
                    'query' => $statement['query'],
                    'bindings' => $statement['bindings'],
                    'explain' => json_decode($plan[0]->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR)[0],
                ];
            }
            $results[$scenario][$operation] = ['result' => $result, 'median_ms' => $samples[3], 'plans' => $plans];
            printf("%-20s %-8s %d SQL %9.2f ms\n", $scenario, $operation, count($plans), $samples[3]);
        }
    }

    if (file_put_contents($output, json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Unable to write query plans.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
