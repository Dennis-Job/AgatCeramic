<?php

namespace App\Support;

final class ProductWorkbookSchema
{
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
