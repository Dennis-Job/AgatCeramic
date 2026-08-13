<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the baseline permission catalogue and role assignments.
     */
    public function run(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'View administrative users', 'code' => 'admin-users.view', 'description' => 'Просмотр сотрудников административной панели.'],
            ['name' => 'Manage administrative users', 'code' => 'admin-users.manage', 'description' => 'Создание, изменение и блокировка сотрудников.'],
            ['name' => 'View roles', 'code' => 'roles.view', 'description' => 'Просмотр ролей и их назначений.'],
            ['name' => 'Manage roles', 'code' => 'roles.manage', 'description' => 'Создание, изменение и удаление ролей.'],
            ['name' => 'View permissions', 'code' => 'permissions.view', 'description' => 'Просмотр каталога прав и назначений ролям.'],
            ['name' => 'Manage permissions', 'code' => 'permissions.manage', 'description' => 'Изменение прав и назначений ролям.'],
            ['name' => 'Manage catalog', 'code' => 'catalog.manage', 'description' => 'Управление категориями, товарами, вариантами и брендами.'],
            ['name' => 'Manage imports', 'code' => 'imports.manage', 'description' => 'Импорт, экспорт и массовые операции каталога.'],
            ['name' => 'View orders', 'code' => 'orders.view', 'description' => 'Просмотр заказов.'],
            ['name' => 'Manage orders', 'code' => 'orders.manage', 'description' => 'Изменение статусов, комментариев и данных заказов.'],
            ['name' => 'Manage payments', 'code' => 'payments.manage', 'description' => 'Ручная фиксация и изменение статусов оплаты.'],
            ['name' => 'View contacts', 'code' => 'contacts.view', 'description' => 'Просмотр обращений клиентов.'],
            ['name' => 'Manage contacts', 'code' => 'contacts.manage', 'description' => 'Обработка и назначение обращений.'],
            ['name' => 'Manage content', 'code' => 'content.manage', 'description' => 'Управление страницами, баннерами и настройками контента.'],
            ['name' => 'Manage media', 'code' => 'media.manage', 'description' => 'Управление медиа-библиотекой.'],
            ['name' => 'Manage SEO', 'code' => 'seo.manage', 'description' => 'Управление SEO-метаданными и редиректами.'],
            ['name' => 'View analytics', 'code' => 'analytics.view', 'description' => 'Просмотр отчётов и аналитики.'],
            ['name' => 'Manage site settings', 'code' => 'settings.manage', 'description' => 'Управление глобальными настройками сайта.'],
            ['name' => 'View audit log', 'code' => 'audit-log.view', 'description' => 'Просмотр журнала действий.'],
        ];

        Permission::query()->upsert(
            array_map(static fn (array $permission): array => [
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ], $permissions),
            ['code'],
            ['name', 'description', 'updated_at'],
        );

        $permissionIds = Permission::query()->pluck('id', 'code');

        foreach ($this->rolePermissionMatrix() as $roleSlug => $codes) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
            $role->permissions()->sync($permissionIds->only($codes)->all());
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissionMatrix(): array
    {
        $all = [
            'admin-users.view', 'admin-users.manage', 'roles.view', 'roles.manage',
            'permissions.view', 'permissions.manage', 'catalog.manage', 'imports.manage',
            'orders.view', 'orders.manage', 'payments.manage', 'contacts.view', 'contacts.manage',
            'content.manage', 'media.manage', 'seo.manage', 'analytics.view', 'settings.manage',
            'audit-log.view',
        ];

        return [
            'super-admin' => $all,
            'administrator' => ['admin-users.view', 'roles.view', 'permissions.view', 'catalog.manage', 'imports.manage', 'orders.view', 'orders.manage', 'payments.manage', 'contacts.view', 'contacts.manage', 'content.manage', 'media.manage', 'seo.manage', 'analytics.view', 'settings.manage', 'audit-log.view'],
            'catalog-manager' => ['catalog.manage', 'imports.manage', 'media.manage'],
            'order-manager' => ['orders.view', 'orders.manage', 'payments.manage', 'contacts.view', 'contacts.manage'],
            'content-manager' => ['content.manage', 'media.manage'],
            'seo-manager' => ['seo.manage', 'content.manage', 'media.manage'],
            'analyst' => ['analytics.view'],
        ];
    }
}
