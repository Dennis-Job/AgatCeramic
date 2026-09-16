<?php

namespace App\Services;

use App\Models\ProductImport;
use App\Models\ProductImportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type GroupChange array{action: 'disband', group_id: int}|array{action: 'create'|'update', group_id: int|null, code: string, name: string, axis_attribute_ids: list<int>, product_ids: list<int>}
 * Dedicated workbook importer; it only changes variation-group membership and axes.
 */
class ProductGroupImportService
{
    public const MAX_ROWS = 5000;

    public function __construct(
        private readonly ProductGroupWorkbookReader $reader,
        private readonly ProductGroupChangePlanner $planner,
        private readonly ProductGroupManagementService $groups,
        private readonly ProductGroupImportTemplateService $templates,
        private readonly ProductGroupImportErrorReportService $errorReports,
    ) {}

    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        return $this->templates->createTemplate();
    }

    public function initialize(ProductImport $import, string $path): void
    {
        $workbook = $this->reader->read($path);
        $errors = $workbook['errors'];
        $changes = $this->planner->build($workbook['groups'], $workbook['members'], $errors);
        if ($errors === [] && $changes !== []) {
            try {
                DB::beginTransaction();
                $actor = $import->user;
                if ($actor === null) {
                    throw new RuntimeException('Не удалось определить автора импорта.');
                }
                $this->groups->applyBulk($actor, $changes);
                DB::rollBack();
            } catch (ValidationException $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $errors[] = ['sheet' => 'Группы', 'row' => 1, 'name' => null, 'messages' => collect($exception->errors())->flatten()->values()->all(), 'values' => []];
            } catch (Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                throw $exception;
            }
        }
        DB::transaction(function () use ($import, $workbook, $errors, $changes): void {
            $import = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($import->total_rows !== 0) {
                return;
            }
            foreach ($errors as $index => $error) {
                $import->rowErrors()->create([
                    // Product import errors have a per-import unique row number; keep
                    // the workbook sheet/real row in values for a useful report.
                    'row_number' => 100000 + (int) $index,
                    'name' => $error['name'],
                    'messages' => array_map(fn (mixed $message): string => "Лист «{$error['sheet']}», строка {$error['row']}: ".(is_string($message) ? $message : ''), $error['messages']),
                    'values' => $error,
                ]);
            }
            if ($errors === [] && $changes !== []) {
                ProductImportItem::query()->create([
                    'product_import_id' => $import->id, 'row_number' => 1, 'name' => 'Группы вариантов',
                    'payload' => ['changes' => $changes], 'attribute_payload' => [], 'status' => 'pending',
                ]);
            }
            $import->forceFill(['total_rows' => count($workbook['groups']), 'failed_rows' => count($errors), 'processed_rows' => count($errors)])->save();
        });
        $import->refresh();
    }

    public function process(ProductImport $import): bool
    {
        $item = $import->items()->where('status', 'pending')->first();
        if ($item === null) {
            return true;
        }
        DB::transaction(function () use ($import, $item): void {
            $item = ProductImportItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'pending') {
                return;
            }
            try {
                $changes = $this->itemChanges($item);
                $actor = $import->user;
                if ($actor === null) {
                    throw new RuntimeException('Не удалось определить автора импорта.');
                }
                $this->groups->applyBulk($actor, $changes);
                $item->forceFill(['status' => 'completed'])->save();
                $import->increment('created_rows', collect($changes)->where('action', 'create')->count());
                $import->increment('updated_rows', collect($changes)->whereIn('action', ['update', 'disband'])->count());
            } catch (ValidationException $exception) {
                $item->forceFill(['status' => 'failed'])->save();
                $import->rowErrors()->create(['row_number' => 1, 'name' => $item->name, 'messages' => collect($exception->errors())->flatten()->values()->all(), 'values' => ['sheet' => 'Группы', 'row' => '—', 'name' => $item->name, 'messages' => collect($exception->errors())->flatten()->values()->all()]]);
                $import->increment('failed_rows');
            }
            $import->increment('processed_rows');
        });

        return true;
    }

    /** @return array{path: string, name: string} */
    public function createErrorReport(ProductImport $import): array
    {
        return $this->errorReports->create($import);
    }

    /** @return list<GroupChange> */
    private function itemChanges(ProductImportItem $item): array
    {
        $payload = $item->getAttribute('payload');
        if (! is_array($payload) || ! isset($payload['changes']) || ! is_array($payload['changes'])) {
            return [];
        }

        $changes = [];
        foreach ($payload['changes'] as $change) {
            if (! is_array($change) || ! isset($change['action']) || ! is_string($change['action'])) {
                continue;
            }
            $groupId = $change['group_id'] ?? null;
            if ($change['action'] === 'disband' && is_int($groupId)) {
                $changes[] = ['action' => 'disband', 'group_id' => $groupId];

                continue;
            }
            $code = $change['code'] ?? null;
            $name = $change['name'] ?? null;
            $axisIds = $this->integerList($change['axis_attribute_ids'] ?? null);
            $productIds = $this->integerList($change['product_ids'] ?? null);
            if (($change['action'] === 'create' || $change['action'] === 'update') && (is_int($groupId) || $groupId === null) && is_string($code) && is_string($name) && $axisIds !== null && $productIds !== null) {
                $changes[] = ['action' => $change['action'], 'group_id' => $groupId, 'code' => $code, 'name' => $name, 'axis_attribute_ids' => $axisIds, 'product_ids' => $productIds];
            }
        }

        return $changes;
    }

    /** @return list<int>|null */
    private function integerList(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }
        $ids = [];
        foreach ($value as $id) {
            if (! is_int($id)) {
                return null;
            }
            $ids[] = $id;
        }

        return $ids;
    }
}
