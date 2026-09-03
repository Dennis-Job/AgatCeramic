<?php

namespace App\Support;

final class ProductWorkbookSchema
{
    public const MANAGER_HEADERS = [
        'sku' => 'SKU', 'article_number' => 'Артикул', 'barcode' => 'Штрихкод',
        'name' => 'Название', 'description' => 'Описание', 'category_name' => 'Категория',
        'brand_name' => 'Бренд', 'unit' => 'Единица продажи', 'price' => 'Цена',
        'old_price' => 'Старая цена', 'stock_quantity' => 'Остаток', 'is_active' => 'Активность',
        'is_on_sale' => 'Распродажа', 'primary_image_url' => 'Основное изображение',
        'product_group_code' => 'Код группы', 'product_group_name' => 'Группа вариантов',
        'created_at' => 'Создан', 'updated_at' => 'Изменён',
    ];

    public const UNIT_LABELS = [
        'piece' => 'Штука', 'square_meter' => 'Квадратный метр', 'linear_meter' => 'Погонный метр',
        'package' => 'Упаковка', 'kilogram' => 'Килограмм', 'liter' => 'Литр', 'set' => 'Комплект',
    ];

    public const BASE_HEADERS = [
        'id', 'sku', 'article_number', 'barcode', 'name', 'slug', 'description',
        'category_id', 'category_slug', 'category_name', 'brand_id', 'brand_slug', 'brand_name',
        'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale',
        'primary_image_url', 'product_group_id', 'product_group_code', 'product_group_name',
        'created_at', 'updated_at',
    ];

    public const REQUIRED_IMPORT_HEADERS = [
        'id', 'sku', 'name', 'slug', 'category_id', 'category_slug', 'brand_id', 'brand_slug',
        'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale',
    ];

    public const WRITABLE_PRODUCT_FIELDS = [
        'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price',
        'stock_quantity', 'is_active', 'is_on_sale',
    ];

    private function __construct() {}
}
