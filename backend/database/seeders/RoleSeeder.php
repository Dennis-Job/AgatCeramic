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
            ['name' => 'Супер Администратор', 'slug' => 'super-admin', 'description' => 'Полный доступ к административной панели.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Администратор', 'slug' => 'administrator', 'description' => 'Операционное администрирование сайта.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Менеджер каталога', 'slug' => 'catalog-manager', 'description' => 'Управление каталогом, товарами и брендами.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Менеджер заказов', 'slug' => 'order-manager', 'description' => 'Обработка заказов и обращений.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Контент-менеджер', 'slug' => 'content-manager', 'description' => 'Управление контентом сайта.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SEO-менеджер', 'slug' => 'seo-manager', 'description' => 'Управление SEO-данными.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Аналитик', 'slug' => 'analyst', 'description' => 'Просмотр аналитики и отчётов.', 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['name', 'description', 'updated_at']);

        Role::query()->whereIn('slug', [
            'super-admin', 'administrator', 'catalog-manager', 'order-manager',
            'content-manager', 'seo-manager', 'analyst',
        ])->update(['is_system' => true]);
    }
}
