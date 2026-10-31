<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/** Explicit allowlists keep customer data, credentials and unrelated model attributes out of audit diffs. */
class AuditSnapshotService
{
    /** @return array<string, mixed>|null */
    public function identity(?Model $entity): ?array
    {
        return match (true) {
            $entity instanceof User => ['name' => $entity->name, 'email' => $entity->email],
            $entity instanceof Product => ['name' => $entity->name, 'sku' => $entity->sku],
            $entity instanceof ProductImage => $this->imageIdentity($entity),
            $entity instanceof Order => ['name' => 'Заказ №'.$entity->order_number, 'order_number' => $entity->order_number],
            $entity instanceof Role => ['name' => $entity->name, 'slug' => $entity->slug],
            $entity instanceof Permission => ['name' => $entity->name, 'code' => $entity->code],
            $entity instanceof ProductCollection, $entity instanceof ProductGroup, $entity instanceof Brand, $entity instanceof Category,
            $entity instanceof Attribute, $entity instanceof AttributeGroup => ['name' => $entity->getAttribute('name')],
            $entity instanceof ProductImport => ['name' => 'Импорт #'.$entity->id, 'import_id' => $entity->id],
            $entity instanceof ProductImageImport => ['name' => 'Импорт фотографий #'.$entity->id, 'import_id' => $entity->id],
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function capture(Model $entity): array
    {
        $fields = match (true) {
            $entity instanceof Product => ['name', 'sku', 'slug', 'article_number', 'barcode', 'category_id', 'brand_id', 'collection_id', 'description', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale'],
            $entity instanceof ProductImage => ['path', 'is_primary', 'sort_order', 'alt'],
            $entity instanceof ProductGroup => ['name', 'code'],
            $entity instanceof User => ['status'],
            $entity instanceof Role => ['name', 'slug', 'description'],
            $entity instanceof ProductCollection => ['brand_id', 'name', 'slug', 'headline', 'description', 'image_id', 'is_active'],
            $entity instanceof Brand => ['name', 'slug', 'description', 'country_code', 'logo_id', 'is_active'],
            $entity instanceof Category => ['name', 'slug', 'description', 'parent_id', 'image_id', 'is_parent', 'is_active', 'uses_collections', 'sort_order'],
            $entity instanceof AttributeGroup => ['name', 'slug', 'description', 'sort_order'],
            $entity instanceof Attribute => ['name', 'slug', 'attribute_group_id', 'type', 'unit', 'is_filterable', 'is_visible_on_product_page', 'sort_order'],
            default => [],
        };
        $state = [];
        foreach ($fields as $field) {
            $value = $entity->getAttribute($field);
            // Catalog titles are public business data. Employee/customer names never enter this path.
            $state[$field === 'name' ? 'title' : $field] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return [...$state, ...$this->relations($entity)];
    }

    /** @return array<string, mixed> */
    private function relations(Model $entity): array
    {
        return match (true) {
            $entity instanceof Product => [
                'attribute_values' => $entity->attributeValues()->with('attribute:id,name')->orderBy('attribute_id')->get()
                    ->map(fn ($value): array => ['id' => $value->attribute_id, 'title' => $value->attribute?->name, 'value' => $value->value])->all(),
                'relations' => $entity->outgoingRelations()->with('relatedProduct:id,sku')->orderBy('related_product_id')->orderBy('type')->get()
                    ->map(fn ($relation): array => ['related_product_id' => $relation->related_product_id, 'sku' => $relation->relatedProduct?->sku, 'type' => $relation->type, 'sort_order' => $relation->sort_order])->all(),
            ],
            $entity instanceof ProductGroup => [
                'product_skus' => $entity->products()->orderBy('products.id')->pluck('sku')->all(),
                'axis_ids' => $entity->axes()->orderByPivot('sort_order')->orderBy('attributes.id')->pluck('attributes.id')->all(),
            ],
            $entity instanceof User => ['roles' => $entity->roles()->orderBy('roles.id')->get()->map(fn (Role $role): string => $role->slug.' · '.$role->name)->all()],
            $entity instanceof Role => ['permissions' => $entity->permissions()->orderBy('permissions.id')->get()->map(fn (Permission $permission): string => $permission->code.' · '.$permission->name)->all()],
            $entity instanceof Brand => ['documents' => $entity->documents()->get()->map(fn ($document): array => ['id' => $document->id, 'sort_order' => $this->pivotValue($document, 'sort_order')])->all()],
            $entity instanceof Category => [
                'documents' => $entity->documents()->get()->map(fn ($document): array => ['id' => $document->id, 'sort_order' => $this->pivotValue($document, 'sort_order')])->all(),
                'attributes' => $entity->attributes()->get()->map(fn (Attribute $attribute): array => ['id' => $attribute->id, 'title' => $attribute->name, 'sort_order' => $this->pivotValue($attribute, 'sort_order'), 'is_required' => (bool) $this->pivotValue($attribute, 'is_required')])->all(),
                'attribute_groups' => $entity->attributeGroups()->get()->map(fn (AttributeGroup $group): array => ['id' => $group->id, 'title' => $group->name, 'sort_order' => $this->pivotValue($group, 'sort_order')])->all(),
            ],
            $entity instanceof Attribute => ['options' => $entity->options()->get()->map(fn ($option): array => ['value' => $option->value, 'label' => $option->label, 'sort_order' => $option->sort_order])->all()],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function imageIdentity(ProductImage $image): array
    {
        $product = $image->product()->first();

        return ['name' => $product?->name, 'sku' => $product?->sku, 'product_id' => $image->product_id, 'image_id' => $image->id, 'file' => basename($image->path)];
    }

    private function pivotValue(Model $entity, string $field): mixed
    {
        $pivot = $entity->getRelation('pivot');

        return $pivot instanceof Model ? $pivot->getAttribute($field) : null;
    }
}
