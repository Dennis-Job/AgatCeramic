<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Attribute
    {
        return DB::transaction(function () use ($actor, $attributes): Attribute {
            $options = Arr::pull($attributes, 'options', []);
            $attribute = Attribute::query()->create($attributes);
            $this->replaceOptions($attribute, $options);
            $this->auditLogService->record($actor, 'attribute.created', $attribute);

            return $attribute->load('options');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Attribute $attribute, array $attributes): Attribute
    {
        return DB::transaction(function () use ($actor, $attribute, $attributes): Attribute {
            $hasOptions = array_key_exists('options', $attributes);
            $options = Arr::pull($attributes, 'options', []);
            $attribute->fill($attributes)->save();

            if (! $attribute->acceptsOptions()) {
                $attribute->options()->delete();
            } elseif ($hasOptions) {
                $this->replaceOptions($attribute, $options);
            }

            $this->auditLogService->record($actor, 'attribute.updated', $attribute);

            return $attribute->load('options');
        });
    }

    public function delete(User $actor, Attribute $attribute): void
    {
        DB::transaction(function () use ($actor, $attribute): void {
            $this->auditLogService->record($actor, 'attribute.deleted', $attribute);
            $attribute->delete();
        });
    }

    /** @param array<int, array<string, mixed>> $options */
    private function replaceOptions(Attribute $attribute, array $options): void
    {
        if (! $attribute->acceptsOptions() && $options !== []) {
            throw ValidationException::withMessages(['options' => ['Options are available only for select and multiselect attributes.']]);
        }

        if ($attribute->acceptsOptions() && $options === []) {
            throw ValidationException::withMessages(['options' => ['Select attributes must have at least one option.']]);
        }

        $attribute->options()->delete();
        $attribute->options()->createMany(array_map(static fn (array $option): array => [
            'value' => $option['value'],
            'label' => $option['label'],
            'sort_order' => $option['sort_order'] ?? 0,
        ], $options));
    }
}
