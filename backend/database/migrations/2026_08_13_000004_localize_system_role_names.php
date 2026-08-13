<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'super-admin' => 'Супер Администратор',
            'administrator' => 'Администратор',
            'catalog-manager' => 'Менеджер каталога',
            'order-manager' => 'Менеджер заказов',
            'content-manager' => 'Контент-менеджер',
            'seo-manager' => 'SEO-менеджер',
            'analyst' => 'Аналитик',
        ] as $slug => $name) {
            DB::table('roles')->where('slug', $slug)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach ([
            'super-admin' => 'Super Admin',
            'administrator' => 'Administrator',
            'catalog-manager' => 'Catalog Manager',
            'order-manager' => 'Order Manager',
            'content-manager' => 'Content Manager',
            'seo-manager' => 'SEO Manager',
            'analyst' => 'Analyst',
        ] as $slug => $name) {
            DB::table('roles')->where('slug', $slug)->update(['name' => $name]);
        }
    }
};
