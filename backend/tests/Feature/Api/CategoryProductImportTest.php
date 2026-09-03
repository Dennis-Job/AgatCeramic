<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\CategoryProductImportService;
use App\Services\ProductImportService;
use App\Services\ProductImportTemplateService;
use App\Services\StorageCleanupService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class CategoryProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_errors_use_russian_column_labels_for_required_and_invalid_values(): void
    {
        Storage::fake('local');
        [$category, , , $boolean] = $this->catalog();
        $boolean->update(['name' => 'Ректификация']);
        $actor = $this->actor();
        $cases = [
            [['unit' => null], 'столбец «Единица продажи» должен быть заполнен.'],
            [['price' => null], 'столбец «Цена» должен быть заполнен.'],
            [['stock_quantity' => 'много'], 'столбец «Остаток» должен содержать целое число.'],
            [['price' => 'нет'], 'Поле «Цена» должно содержать число.'],
            [['old_price' => 10], 'Значение поля «Старая цена» должно быть не меньше 25.'],
            [['attribute.rectified' => 'не знаю'], 'столбец «Ректификация» должен содержать Да/Нет'],
        ];
        $rows = array_map(fn ($case, $index) => array_replace($this->values('Проверка '.$index), $case[0]), $cases, array_keys($cases));
        $import = $this->storedImport($actor, $category, $rows);
        app(CategoryProductImportService::class)->process($import, Storage::disk('local')->path($import->path));
        $errors = $import->rowErrors()->get();
        $this->assertCount(count($cases), $errors);
        foreach ($cases as $index => [, $message]) {
            $this->assertStringContainsString($message, $errors[$index]->messages[0]);
            $this->assertSame($index + 2, $errors[$index]->row_number);
        }
    }

    public function test_blank_and_populated_templates_preserve_excel_compatible_structure(): void
    {
        [$category] = $this->catalog();
        foreach ([[], [$this->values('Проверка Excel')]] as $rows) {
            $file = app(ProductImportTemplateService::class)->create($category, $rows);
            $zip = new ZipArchive;
            $zip->open($file['path']);
            try {
                $sheet = $this->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
                $sheet->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $previousColumn = 0;
                foreach ($sheet->query('/s:worksheet/s:cols/s:col') as $column) {
                    $this->assertGreaterThan($previousColumn, (int) $column->getAttribute('min'), 'Column ranges must be ordered and must not overlap.');
                    $previousColumn = (int) $column->getAttribute('max');
                }
                $this->assertCount(1, $sheet->query('/s:worksheet/s:dataValidations/following-sibling::s:legacyDrawing'));
                $this->assertSame('Название *', $sheet->evaluate('string(/s:worksheet/s:sheetData/s:row[@r="1"]/s:c[@r="A1"]/s:is/s:t)'));
                $this->assertCount(count($rows) + 1, $sheet->query('/s:worksheet/s:sheetData/s:row'));
                if ($rows !== []) {
                    $this->assertSame('Проверка Excel', $sheet->evaluate('string(/s:worksheet/s:sheetData/s:row[@r="2"]/s:c[@r="A2"]/s:is/s:t)'));
                }
                $styles = $this->xml($zip->getFromName('xl/styles.xml'));
                $styles->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($styles->query('//*[@rgb]') as $color) {
                    $this->assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $color->getAttribute('rgb'), 'SpreadsheetML colors must contain four ARGB bytes.');
                }
                $this->assertCount(1, $styles->query('/s:styleSheet/s:fonts/s:font[s:b]/s:b/following-sibling::s:sz'));
                foreach ($styles->query('/s:styleSheet/s:fonts/s:font') as $font) {
                    $this->assertCount(1, $styles->query('s:sz/following-sibling::s:color/following-sibling::s:name', $font));
                }
            } finally {
                $zip->close();
                unlink($file['path']);
            }
        }
    }

    public function test_category_template_has_hidden_ids_and_stop_dropdowns_for_all_5000_rows(): void
    {
        [$category, $color, $features, $boolean] = $this->catalog();
        $other = Attribute::factory()->create(['slug' => 'unrelated-attribute']);
        $response = $this->actingAs($this->actor())->get('/api/v1/admin/products/import-template?category_id='.$category->id)->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $workbook = $this->xml($zip->getFromName('xl/workbook.xml'));
        $this->assertSame('hidden', $workbook->query('//*[local-name()="sheet" and @name="Справочники"]')->item(0)->getAttribute('state'));
        $sheet = $this->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
        $styles = $this->xml($zip->getFromName('xl/styles.xml'));
        foreach ([3, 4] as $column) {
            $columnStyle = $sheet->query('//*[local-name()="col" and @min="'.$column.'" and @max="'.$column.'"]')->item(0);
            $style = (int) $columnStyle->getAttribute('style');
            $format = $styles->query('//*[local-name()="cellXfs"]/*[local-name()="xf"]')->item($style);
            $this->assertSame('49', $format->getAttribute('numFmtId'));
        }
        $validations = $sheet->query('//*[local-name()="dataValidation"]');
        $this->assertCount(8, $validations);
        foreach ($validations as $validation) {
            $this->assertSame('stop', $validation->getAttribute('errorStyle'));
            $this->assertSame('1', $validation->getAttribute('showErrorMessage'));
            $this->assertMatchesRegularExpression('/^[A-Z]+2:[A-Z]+5001$/', $validation->getAttribute('sqref'));
            $name = $validation->textContent;
            $this->assertCount(1, $workbook->query('//*[local-name()="definedName" and @name="'.$name.'"]'));
        }
        $headers = app(ProductImportTemplateService::class)->headers($category);
        $this->assertArrayNotHasKey('sku', $headers);
        $this->assertArrayHasKey('slug', $headers);
        $this->assertArrayHasKey('attribute.color', $headers);
        $this->assertArrayHasKey('attribute.features.2', $headers);
        $this->assertArrayNotHasKey('attribute.'.$other->slug, $headers);
        $lookup = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('Белый', $lookup);
        $this->assertStringContainsString('<v>'.$color->options->first()->id.'</v>', $lookup);
        $zip->close();
        unlink($path);
    }

    public function test_template_import_keeps_valid_rows_reports_named_errors_and_reimports_only_corrected_rows(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$category, $color, $features, $boolean] = $this->catalog();
        $actor = $this->actor();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Уже существует', 'slug' => 'taken']);
        $valid = $this->values('Белая плитка') + ['attribute.color' => 'Белый', 'attribute.features.1' => 'Матовая', 'attribute.features.2' => 'Фактура; "камень"', 'attribute.rectified' => 'Нет'];
        $rows = [
            $valid,
            $this->values('Неверный цвет') + ['attribute.color' => 'Зелёный'],
            $this->values('Уже существует'),
            $this->values('Занятый slug') + ['slug' => 'taken'],
            $this->values('Белая плитка'),
            $this->values('Неверное число') + ['stock_quantity' => -5],
            $this->values('Неверное булево') + ['attribute.rectified' => 'Иногда'],
        ];
        $import = $this->storedImport($actor, $category, $rows);
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(7, $import->total_rows);
        $this->assertSame(7, $import->processed_rows);
        $this->assertSame(1, $import->created_rows);
        $this->assertSame(6, $import->failed_rows);
        $this->assertDatabaseMissing('products', ['name' => 'Неверный цвет']);
        $created = Product::query()->where('name', 'Белая плитка')->sole();
        $this->assertSame('belaya-plitka', $created->slug);
        $this->assertMatchesRegularExpression('/^7\d{6}$/', $created->sku);
        $this->assertSame(['matte', 'stone'], $created->attributeValues()->where('attribute_id', $features->id)->value('value'));
        $this->assertFalse($created->attributeValues()->where('attribute_id', $boolean->id)->value('value'));
        $this->assertDatabaseCount('attribute_options', 4);
        $this->actingAs($actor)->getJson('/api/v1/admin/product-imports/'.$import->id)
            ->assertOk()->assertJsonPath('data.has_error_file', true)
            ->assertJsonPath('data.row_errors.0.name', 'Неверный цвет')
            ->assertJsonPath('data.row_errors.0.row', 3);
        $response = $this->get('/api/v1/admin/product-imports/'.$import->id.'/errors')->assertOk();
        $errorPath = $response->baseResponse->getFile()->getPathname();
        $errorRows = app(ProductImportTemplateService::class)->read($category, $errorPath);
        $this->assertCount(6, $errorRows);
        $this->assertSame('Неверный цвет', $errorRows[0]['values']['name']);
        $this->assertSame('Зелёный', $errorRows[0]['values']['attribute.color']);
        $fixed = $errorRows[0]['values'];
        $fixed['attribute.color'] = 'Серый';
        $retry = $this->storedImport($actor, $category, [$fixed]);
        (new ProcessProductImport($retry->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $this->assertSame(1, $retry->refresh()->created_rows);
        $this->assertSame(0, $retry->failed_rows);
        $this->assertDatabaseCount('products', 3);
        $this->actingAs($this->actor())->get('/api/v1/admin/product-imports/'.$import->id.'/errors')->assertNotFound();
        unlink($errorPath);
    }

    public function test_checkpoint_resumes_after_interruption_and_chunks_large_workbooks_without_duplicates(): void
    {
        Storage::fake('local');
        Queue::fake();
        $category = Category::factory()->create(['sku_prefix' => '8']);
        $actor = $this->actor();
        $rows = array_map(fn ($index) => $this->values('Товар '.$index), range(1, 101));
        $import = $this->storedImport($actor, $category, $rows);
        $service = app(CategoryProductImportService::class);
        $this->assertFalse($service->process($import, Storage::disk('local')->path($import->path)));
        $this->assertSame(100, $import->refresh()->created_rows);
        $this->assertSame(101, $import->last_processed_row);
        // A new service run sees exactly the persisted checkpoint, as after a worker interruption.
        $this->assertTrue($service->process($import->fresh('user'), Storage::disk('local')->path($import->path)));
        $this->assertSame(101, $import->refresh()->created_rows);
        $this->assertSame(0, $import->failed_rows);
        $this->assertDatabaseCount('products', 101);
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $this->assertSame('completed', $import->refresh()->status);
        $this->assertDatabaseCount('products', 101);
    }

    public function test_template_rejects_wrong_category_invalid_shape_and_more_than_5000_rows_before_any_writes(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $template = app(ProductImportTemplateService::class);
        $file = $template->create($category, [$this->values('Test')]);
        try {
            $template->read($other, $file['path']);
            $this->fail('The category metadata must match.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('выбранной категории', $exception->getMessage());
        } finally {
            unlink($file['path']);
        }
        $rows = array_fill(0, 5001, $this->values('Too many'));
        $file = $template->create($category, $rows);
        try {
            $template->read($category, $file['path']);
            $this->fail('The row limit must apply before writes.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('5000', $exception->getMessage());
        } finally {
            unlink($file['path']);
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_template_and_failed_rows_download_require_import_permission(): void
    {
        $category = Category::factory()->create();
        $this->getJson('/api/v1/admin/products/import-template?category_id='.$category->id)->assertUnauthorized();
        $this->actingAs($this->actor('catalog.manage'))->getJson('/api/v1/admin/products/import-template?category_id='.$category->id)->assertForbidden();
        $this->actingAs($this->actor())->getJson('/api/v1/admin/products/import-template?category_id=999999')->assertUnprocessable();
    }

    public function test_sparse_rows_keep_physical_excel_row_numbers_and_enforce_the_last_template_row(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create(['sku_prefix' => '8']);
        $import = $this->storedImport($this->actor(), $category, [[], [], [], ['name' => 'Ошибка на строке 5']]);
        app(CategoryProductImportService::class)->process($import, Storage::disk('local')->path($import->path));
        $this->assertSame(1, $import->refresh()->total_rows);
        $this->assertSame(5, $import->rowErrors()->sole()->row_number);

        $rows = array_fill(0, 5000, []);
        $rows[] = $this->values('Товар на строке 5002');
        $file = app(ProductImportTemplateService::class)->create($category, $rows);
        try {
            app(ProductImportTemplateService::class)->read($category, $file['path']);
            $this->fail('A sparse row after row 5001 must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('5001', $exception->getMessage());
        } finally {
            unlink($file['path']);
        }
    }

    public function test_stock_exceeding_postgresql_integer_range_fails_only_that_row(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create(['sku_prefix' => '8']);
        $import = $this->storedImport($this->actor(), $category, [
            $this->values('Первый товар'),
            $this->values('Слишком большой остаток') + ['stock_quantity' => 2147483648],
            $this->values('Последний товар'),
        ]);
        app(CategoryProductImportService::class)->process($import, Storage::disk('local')->path($import->path));
        $this->assertSame(2, $import->refresh()->created_rows);
        $this->assertSame(1, $import->failed_rows);
        $this->assertStringContainsString('2147483647', $import->rowErrors()->sole()->messages[0]);
        $this->assertDatabaseHas('products', ['name' => 'Последний товар']);
    }

    private function catalog(): array
    {
        $category = Category::factory()->create(['name' => 'Плитка', 'sku_prefix' => '7']);
        $color = Attribute::factory()->create(['slug' => 'color', 'name' => 'Цвет', 'type' => 'select', 'unit' => null]);
        $color->options()->createMany([['label' => 'Белый', 'value' => 'white'], ['label' => 'Серый', 'value' => 'gray']]);
        $features = Attribute::factory()->create(['slug' => 'features', 'name' => 'Свойства', 'type' => 'multiselect', 'unit' => null]);
        $features->options()->createMany([['label' => 'Матовая', 'value' => 'matte'], ['label' => 'Фактура; "камень"', 'value' => 'stone']]);
        $boolean = Attribute::factory()->create(['slug' => 'rectified', 'type' => 'boolean', 'unit' => null]);
        $category->attributes()->attach([$color->id, $features->id, $boolean->id]);

        return [$category, $color, $features, $boolean];
    }

    private function values(string $name): array
    {
        return ['name' => $name, 'unit' => 'Штука', 'price' => 25];
    }

    private function storedImport(User $actor, Category $category, array $rows): ProductImport
    {
        $file = app(ProductImportTemplateService::class)->create($category, $rows);
        $path = 'product-imports/'.basename($file['path']).'.xlsx';
        Storage::disk('local')->put($path, file_get_contents($file['path']));
        unlink($file['path']);

        return ProductImport::query()->create([
            'user_id' => $actor->id, 'category_id' => $category->id, 'original_filename' => 'products.xlsx',
            'disk' => 'local', 'path' => $path, 'status' => 'pending',
        ]);
    }

    private function actor(string $permission = 'imports.manage'): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', $permission)->sole());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function xml(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        return new DOMXPath($document);
    }
}
