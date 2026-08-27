<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Services\ProductCompletenessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MigrateStandaloneProducts extends Command
{
    /** @var array<int, array{disk:string,path:string}> */
    private array $copiedFiles = [];

    protected $signature = 'catalog:migrate-standalone-products {--apply : Persist conversion} {--finalize : Remove verified converted legacy variant rows}';

    protected $description = 'Preview or convert legacy product variants into standalone sellable products.';

    public function handle(): int
    {
        if (! Schema::hasTable('product_variants')) {
            $this->info('Legacy variant tables have already been removed.');

            return self::SUCCESS;
        }

        if ($this->option('finalize')) {
            return $this->finalize();
        }

        $apply = (bool) $this->option('apply');
        $products = Product::query()->whereHas('variants', fn ($query) => $query->whereNull('standalone_product_id'))
            ->with(['variants.attributeValues', 'attributeValues', 'images'])->orderBy('id')->get();
        $orphans = Product::query()->whereDoesntHave('variants')->whereNull('sku')->count();
        $this->line(($apply ? 'APPLY' : 'DRY-RUN').": {$products->count()} legacy product cards; {$orphans} zero-variant cards will remain inactive.");

        foreach ($products as $product) {
            if ($product->sku !== null) {
                $this->warn("SKIP product {$product->id}: standalone commercial fields are already populated.");

                continue;
            }
            $variants = $product->variants->whereNull('standalone_product_id')->values();
            [$canGroup, $axisIds, $reason] = $this->inferAxes($variants->all());
            $this->line("Product {$product->id}: {$variants->count()} offers; ".($canGroup ? 'group axes '.implode(',', $axisIds) : "no group ({$reason})"));
            if (! $apply) {
                continue;
            }

            $this->copiedFiles = [];
            try {
                DB::transaction(function () use ($product, $variants, $canGroup, $axisIds): void {
                    $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                    $baseName = $locked->name;
                    $baseSlug = $locked->slug;
                    $baseActive = $locked->is_active;
                    $memberIds = [];
                    foreach ($variants as $index => $variant) {
                        $offer = $index === 0 ? $locked : Product::query()->create([
                            'category_id' => $locked->category_id,
                            'brand_id' => $locked->brand_id,
                            'name' => "{$baseName} — {$variant->name}",
                            'slug' => $this->uniqueSlug($baseSlug.'-'.Str::slug($variant->sku)),
                            'description' => $locked->description,
                            'is_active' => false,
                        ]);
                        $offer->fill([
                            'name' => "{$baseName} — {$variant->name}",
                            'slug' => $this->uniqueSlug(
                                $baseSlug.'-'.Str::slug($variant->sku),
                                $offer->id,
                            ),
                            'sku' => $variant->sku, 'article_number' => $variant->article_number,
                            'barcode' => $variant->barcode, 'unit' => $variant->unit,
                            'price' => $variant->price, 'old_price' => $variant->old_price,
                            'stock_quantity' => $variant->stock_quantity,
                            'is_active' => false,
                        ])->save();

                        if ($index !== 0) {
                            foreach ($product->attributeValues as $value) {
                                $offer->attributeValues()->create(['attribute_id' => $value->attribute_id, 'value' => $value->value]);
                            }
                            $this->cloneImages($product, $offer);
                        }
                        foreach ($variant->attributeValues as $value) {
                            $offer->attributeValues()->updateOrCreate(['attribute_id' => $value->attribute_id], ['value' => $value->value]);
                        }
                        if ($variant->is_active && $baseActive) {
                            try {
                                app(ProductCompletenessService::class)->assertCanActivate($offer->load('category'));
                                $offer->update(['is_active' => true]);
                            } catch (ValidationException) {
                                $this->warn("Product {$offer->id} remains inactive because required standalone data is incomplete.");
                            }
                        }
                        $variant->update(['standalone_product_id' => $offer->id]);
                        $memberIds[] = $offer->id;
                    }
                    if ($canGroup && count($memberIds) >= 2) {
                        $group = ProductGroup::query()->firstOrCreate(
                            ['code' => 'legacy-'.$product->id],
                            ['name' => $product->name],
                        );
                        $group->axes()->sync(collect($axisIds)->mapWithKeys(fn (int $id, int $i): array => [$id => ['sort_order' => $i]])->all());
                        $group->products()->sync($memberIds);
                    }
                });
            } catch (\Throwable $exception) {
                foreach ($this->copiedFiles as $copied) {
                    Storage::disk($copied['disk'])->delete($copied['path']);
                }
                throw $exception;
            }
            if ($this->copiedFiles !== []) {
                $this->warn("Product {$product->id}: cloned galleries require visual review.");
            }
        }

        if ($apply) {
            Product::query()->whereDoesntHave('variants')->whereNull('sku')->update(['is_active' => false]);
        }

        return self::SUCCESS;
    }

    private function inferAxes(array $variants): array
    {
        if (count($variants) < 2) {
            return [false, [], 'fewer than two variants'];
        }
        $axisIds = collect($variants)->flatMap(fn (ProductVariant $variant) => $variant->attributeValues->pluck('attribute_id'))->unique()->sort()->values();
        if ($axisIds->isEmpty()) {
            return [false, [], 'no distinguishing attributes'];
        }
        $axes = Attribute::query()->whereKey($axisIds)->get();
        if ($axes->count() !== $axisIds->count() || $axes->contains(fn (Attribute $axis) => in_array($axis->type, ['text', 'multiselect'], true))) {
            return [false, $axisIds->all(), 'unsupported or missing axis'];
        }
        $tuples = [];
        foreach ($variants as $variant) {
            $values = $variant->attributeValues->keyBy('attribute_id');
            if ($axisIds->contains(fn (int $id) => ! $values->has($id))) {
                return [false, $axisIds->all(), 'incomplete axis values'];
            }
            $tuple = json_encode($axisIds->map(fn (int $id) => $values[$id]->value)->all(), JSON_THROW_ON_ERROR);
            if (isset($tuples[$tuple])) {
                return [false, $axisIds->all(), 'duplicate axis tuple'];
            }
            $tuples[$tuple] = true;
        }

        return [true, $axisIds->all(), ''];
    }

    private function cloneImages(Product $source, Product $target): void
    {
        foreach ($source->images as $image) {
            $extension = pathinfo($image->path, PATHINFO_EXTENSION);
            $path = "product-images/{$target->id}/".Str::uuid().($extension ? ".{$extension}" : '');
            if (! Storage::disk($image->disk)->copy($image->path, $path)) {
                throw new \RuntimeException("Unable to clone {$image->path} for product {$target->id}.");
            }
            $this->copiedFiles[] = ['disk' => $image->disk, 'path' => $path];
            $target->images()->create([
                'disk' => $image->disk, 'path' => $path, 'mime_type' => $image->mime_type,
                'size' => $image->size, 'alt' => $image->alt, 'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ]);
        }
    }

    private function uniqueSlug(string $base, ?int $ignoreProductId = null): string
    {
        $slug = Str::limit($base, 230, '');
        $candidate = $slug;
        $suffix = 2;
        while (Product::query()
            ->where('slug', $candidate)
            ->when($ignoreProductId !== null, fn ($query) => $query->whereKeyNot($ignoreProductId))
            ->exists()) {
            $candidate = "{$slug}-".$suffix++;
        }

        return $candidate;
    }

    private function finalize(): int
    {
        if (! $this->option('apply')) {
            $this->error('--finalize requires --apply.');

            return self::FAILURE;
        }
        $remaining = ProductVariant::query()->whereNull('standalone_product_id')->count();
        if ($remaining > 0) {
            $this->error("Refusing finalization: {$remaining} legacy variants are not converted.");

            return self::FAILURE;
        }
        $count = ProductVariant::query()->count();
        ProductVariant::query()->delete();
        $this->info("Removed {$count} verified converted legacy variant rows. Legacy tables remain for a later schema cleanup migration.");

        return self::SUCCESS;
    }
}
