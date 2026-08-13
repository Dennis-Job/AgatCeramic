<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->names() as $code => $name) {
            DB::table('permissions')->where('code', $code)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->names()) as $code) {
            DB::table('permissions')->where('code', $code)->update(['name' => $code]);
        }
    }

    /** @return array<string, string> */
    private function names(): array
    {
        return [
            'admin-users.view' => 'Просмотр сотрудников', 'admin-users.manage' => 'Управление сотрудниками',
            'roles.view' => 'Просмотр ролей', 'roles.manage' => 'Управление ролями',
            'permissions.view' => 'Просмотр прав', 'permissions.manage' => 'Управление правами',
            'catalog.manage' => 'Управление каталогом', 'imports.manage' => 'Управление импортом',
            'orders.view' => 'Просмотр заказов', 'orders.manage' => 'Управление заказами',
            'payments.manage' => 'Управление оплатами', 'contacts.view' => 'Просмотр обращений',
            'contacts.manage' => 'Управление обращениями', 'content.manage' => 'Управление контентом',
            'media.manage' => 'Управление медиа', 'seo.manage' => 'Управление SEO',
            'analytics.view' => 'Просмотр аналитики', 'settings.manage' => 'Управление настройками сайта',
            'audit-log.view' => 'Просмотр журнала аудита',
        ];
    }
};
