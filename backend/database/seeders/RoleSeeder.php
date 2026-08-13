<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the baseline administrative roles without assigning permissions.
     */
    public function run(): void
    {
        $now = now();

        Role::query()->upsert([
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Полный доступ к административной панели.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Операционное администрирование сайта.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Catalog Manager', 'slug' => 'catalog-manager', 'description' => 'Управление каталогом, товарами и брендами.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Order Manager', 'slug' => 'order-manager', 'description' => 'Обработка заказов и обращений.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Content Manager', 'slug' => 'content-manager', 'description' => 'Управление контентом сайта.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SEO Manager', 'slug' => 'seo-manager', 'description' => 'Управление SEO-данными.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Analyst', 'slug' => 'analyst', 'description' => 'Просмотр аналитики и отчётов.', 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['name', 'description', 'updated_at']);
    }
}
