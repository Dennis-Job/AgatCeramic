<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ProductPayload from ProductImportService
 * @phpstan-import-type AttributePayload from ProductImportService
 *
 * @phpstan-type ImportRow array{row: int, values: list<mixed>}
 * @phpstan-type PreparedRow array{row: int, name: ?string, product: ?Product, payload: ProductPayload, attributePayload: AttributePayload}
 * @phpstan-type InspectionError array{row: int, name: ?string, messages: list<string>, values: array<string, mixed>}
 */
final class ProductImportPlanBuilder
{
    public function __construct(
        private readonly ProductImportRowMapper $mapper,
        private readonly ProductImportValueParser $values,
        private readonly ProductImportGroupValidationService $groupValidator,
        private readonly CatalogAttributeIntegrityService $integrityService,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  list<ImportRow>  $rows
     * @param  array<string, Attribute>  $attributes
     * @param  array<string, array<string, mixed>>  $seoProducts
     * @param  null|list<InspectionError>  $errors
     *
     * @param-out null|list<InspectionError> $errors
     *
     * @return list<PreparedRow>
     */
    public function build(array $headers, array $rows, array $attributes, array $seoProducts, bool $localized, ?array &$errors = null): array
    {
        $seen = ['product' => [], 'sku' => [], 'slug' => [], 'article_number' => [], 'barcode' => []];
        $prepared = [];
        foreach ($rows as $entry) {
            $row = $entry['row'];
            $rowValues = [];
            try {
                $rowValues = array_combine($headers, $entry['values']);
                $product = $this->mapper->resolveProduct($rowValues, $row);
                if ($localized) {
                    $rowValues = $this->mapper->localize($rowValues, $seoProducts, $product, $row);
                }
                $category = $this->mapper->resolveCategory($rowValues, $row);
                $brand = $this->mapper->resolveBrand($rowValues, $row);
                $payload = $this->mapper->productPayload($rowValues, $category, $brand, $product, $row, array_keys($seen['product']));
                $attributePayload = $this->mapper->attributePayload($rowValues, $attributes, $row, $localized);

                foreach (['product' => $product?->id, 'sku' => $this->values->nullableString($rowValues['sku'] ?? null),
                    'slug' => $payload['slug'], 'article_number' => $payload['article_number'], 'barcode' => $payload['barcode']] as $field => $value) {
                    if ($value === null) {
                        continue;
                    }
                    if (isset($seen[$field][$value])) {
                        $this->values->rowError($row, "значение {$field} уже указано в строке {$seen[$field][$value]}.");
                    }
                    $seen[$field][$value] = $row;
                }

                if ($product !== null) {
                    $this->groupValidator->validate($product, $payload, $attributePayload);
                }
                $candidate = new Product($payload);
                $candidate->setRelation('category', $category);
                $this->integrityService->assertValuesMatchCategory($candidate, $attributePayload, 'attributes', $payload['is_active']);
                $prepared[] = ['row' => $row, 'name' => $this->values->nullableString($rowValues['name'] ?? null), 'product' => $product, 'payload' => $payload, 'attributePayload' => $attributePayload];
            } catch (ValidationException $exception) {
                $messages = $this->validationMessages($exception);
                if ($errors === null) {
                    $message = $messages[0] ?? 'Строка содержит недопустимое значение.';
                    if (str_starts_with($message, "Строка {$row}:")) {
                        throw $exception;
                    }
                    $this->values->rowError($row, $message);
                }
                $errors[] = [
                    'row' => $row,
                    'name' => $this->values->nullableString($rowValues['name'] ?? null),
                    'messages' => array_map(fn (string $message): string => preg_replace('/^Строка '.preg_quote((string) $row, '/').':\\s*/u', '', $message) ?? $message, $messages),
                    'values' => $rowValues,
                ];
            }
        }

        return $prepared;
    }

    /** @return list<string> */
    private function validationMessages(ValidationException $exception): array
    {
        $messages = [];
        foreach ($exception->errors() as $fieldMessages) {
            if (! is_array($fieldMessages)) {
                continue;
            }
            foreach ($fieldMessages as $message) {
                if (is_string($message)) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }
}
