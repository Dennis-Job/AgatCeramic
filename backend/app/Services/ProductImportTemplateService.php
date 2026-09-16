<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;

class ProductImportTemplateService
{
    public const BASE_HEADERS = ProductImportTemplateSchema::BASE_HEADERS;

    public const EDIT_HEADERS = ProductImportTemplateSchema::EDIT_HEADERS;

    public function __construct(
        private readonly ProductImportTemplateSchema $schema,
        private readonly ProductImportTemplateReader $reader,
        private readonly ProductImportTemplateWriter $writer,
    ) {}

    /** @return array<string, string> */
    public function headers(Category $category, bool $editing = false): array
    {
        return $this->schema->headers($category, $editing);
    }

    /** @return list<array<string, mixed>> */
    public function editingRows(Category $category): array
    {
        return $this->schema->editingRows($category);
    }

    public function optionLabel(Attribute $attribute, AttributeOption $option): string
    {
        return $this->schema->optionLabel($attribute, $option);
    }

    /** @param iterable<array<string, mixed>> $rows
     * @return array{path: string, name: string}
     */
    public function create(Category $category, iterable $rows = [], bool $editing = false): array
    {
        return $this->writer->create($category, $rows, $editing);
    }

    /** @return list<array{row: int, editing: bool, values: array<string, mixed>}> */
    public function read(Category $category, string $path): array
    {
        return $this->reader->read($category, $path);
    }
}
