<?php

namespace Tests\Unit\Services;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

class ProductImportTemplateCategoryValidatorTest extends TestCase
{
    public function test_failed_workbook_open_does_not_leave_shared_string_cache_files(): void
    {
        $directory = sys_get_temp_dir().'/agat-category-test-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        mkdir($directory.'/cache', 0700);
        $path = $directory.'/invalid.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets/></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdShared" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" uniqueCount="100000"><si><t>cached value</t></si></sst>');
        $zip->close();

        // The separate runtime forces OpenSpout's file cache without changing this test process.
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$category = new App\Models\Category;
$category->id = 1;
try {
    $app->make(App\Services\ProductImportTemplateCategoryValidator::class)->validate($category, $argv[2]);
    exit(1);
} catch (Illuminate\Validation\ValidationException) {
    echo 'rejected';
}
PHP;
        try {
            $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'sys_temp_dir='.$directory.'/cache', '-r', $script, dirname(__DIR__, 3), $path]);
            $process->mustRun();
            $this->assertSame('rejected', $process->getOutput());
            $this->assertSame([], (new Filesystem)->allFiles($directory.'/cache'));
            $this->assertSame([], (new Filesystem)->directories($directory.'/cache'));
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }
}
