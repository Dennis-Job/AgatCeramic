<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductGroup;
use RuntimeException;

/**
 * @phpstan-import-type GroupRow from ProductGroupWorkbookReader
 * @phpstan-import-type MemberRow from ProductGroupWorkbookReader
 * @phpstan-import-type ImportError from ProductGroupWorkbookReader
 *
 * @phpstan-type GroupChange array{action: 'disband', group_id: int}|array{action: 'create'|'update', group_id: int|null, code: string, name: string, axis_attribute_ids: list<int>, product_ids: list<int>}
 */
final class ProductGroupChangePlanner
{
    private const ACTIONS = ['не изменять' => 'none', 'создать' => 'create', 'изменить' => 'update', 'расформировать' => 'disband'];

    public function __construct(private readonly ProductGroupAxisChoices $axisChoices) {}

    /**
     * @param  list<GroupRow>  $groups
     * @param  array<string, list<MemberRow>>  $members
     * @param  list<ImportError>  $errors
     *
     * @param-out list<ImportError> $errors
     *
     * @return list<GroupChange>
     */
    public function build(array $groups, array $members, array &$errors): array
    {
        $changes = [];
        $keys = [];
        $targetCodes = [];
        $axisChoices = $this->axisChoices->forAttributes(Attribute::query()->whereNotIn('type', ['text', 'multiselect'])->orderBy('name')->orderBy('id')->get());
        $axisIdsByLabel = array_flip($axisChoices);
        foreach ($groups as $row) {
            $action = self::ACTIONS[mb_strtolower($row['action'])] ?? null;
            if ($action === null) {
                $errors[] = $this->error($row, 'Выберите действие: «Не изменять», «Создать», «Изменить» или «Расформировать».');

                continue;
            }
            if ($action === 'none') {
                continue;
            }
            if ($row['key'] === '') {
                $errors[] = $this->error($row, 'Укажите ключ группы.');

                continue;
            }
            if (isset($keys[$row['key']])) {
                $errors[] = $this->error($row, 'Ключ группы повторяется на листе «Группы».');

                continue;
            }
            $keys[$row['key']] = true;
            $source = $row['source'] !== '' ? $row['source'] : $row['key'];
            $group = $action === 'create' ? null : ProductGroup::query()->where('code', $source)->first();
            if ($action !== 'create' && $group === null) {
                $errors[] = $this->error($row, "Группа с исходным кодом «{$source}» не найдена.");

                continue;
            }
            if ($action === 'disband') {
                $changes[] = ['action' => 'disband', 'group_id' => $group->id];

                continue;
            }
            if ($row['code'] === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $row['code'])) {
                $errors[] = $this->error($row, 'Укажите код группы латиницей, цифрами, точкой, дефисом или подчёркиванием.');

                continue;
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 255) {
                $errors[] = $this->error($row, 'Укажите название группы длиной до 255 символов.');

                continue;
            }
            if (isset($targetCodes[$row['code']])) {
                $errors[] = $this->error($row, 'Итоговый код группы повторяется в файле.');

                continue;
            }
            $targetCodes[$row['code']] = true;
            $axisIds = [];
            foreach ($row['axes'] as $axis) {
                if (isset($axisIdsByLabel[$axis])) {
                    $axisIds[] = (int) $axisIdsByLabel[$axis];

                    continue;
                }
                if (! preg_match('/^(\d+)\s*:/', $axis, $match) || ! isset($axisChoices[(int) $match[1]])) {
                    $errors[] = $this->error($row, 'Выберите ось из выпадающего списка шаблона.');

                    continue 2;
                }
                $axisIds[] = (int) $match[1];
            }
            if ($axisIds === [] || count($axisIds) !== count(array_unique($axisIds))) {
                $errors[] = $this->error($row, 'Укажите от одной до двадцати неповторяющихся осей.');

                continue;
            }
            $memberRows = $members[$row['key']] ?? [];
            if (count($memberRows) < 2 || count($memberRows) > 500) {
                $errors[] = $this->error($row, 'Для создания или изменения укажите полный состав из 2–500 SKU на листе «Состав».');

                continue;
            }
            $skus = array_column($memberRows, 'sku');
            if (count($skus) !== count(array_unique($skus))) {
                $errors[] = $this->error($row, 'SKU не должен повторяться в составе одной группы.');

                continue;
            }
            $products = Product::query()->whereIn('sku', $skus)->get()->keyBy('sku');
            if ($products->count() !== count($skus)) {
                $errors[] = $this->error($row, 'Один или несколько SKU из состава не найдены.');

                continue;
            }
            $changes[] = [
                'action' => $action, 'group_id' => $group?->id, 'code' => $row['code'], 'name' => $row['name'],
                'axis_attribute_ids' => $axisIds,
                'product_ids' => array_values(collect($skus)->map(function (string $sku) use ($products): int {
                    $product = $products->get($sku);
                    if (! $product instanceof Product) {
                        throw new RuntimeException('Не удалось определить товар для группы вариантов.');
                    }

                    return $product->id;
                })->all()),
            ];
        }
        $seenProducts = [];
        foreach ($changes as $change) {
            foreach ($change['product_ids'] ?? [] as $id) {
                if (isset($seenProducts[$id])) {
                    $errors[] = ['sheet' => 'Состав', 'row' => '—', 'name' => (string) $id, 'messages' => ['SKU включён в состав нескольких итоговых групп.'], 'values' => []];
                }
                $seenProducts[$id] = true;
            }
        }

        return $changes;
    }

    /** @param GroupRow $row
     * @return ImportError
     */
    private function error(array $row, string $message): array
    {
        return ['sheet' => 'Группы', 'row' => $row['row'], 'name' => $row['key'] ?: null, 'messages' => [$message], 'values' => $row];
    }
}
