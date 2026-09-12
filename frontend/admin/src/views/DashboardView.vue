<script setup lang="ts">
import { ArrowRight, ChevronDown, CircleHelp, FilePlus2, MessageSquare, Package, RussianRuble, ShoppingCart, Upload } from '@lucide/vue'
import PageHeader from '../components/shared/PageHeader.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiTable from '../components/ui/UiTable.vue'

const metrics = [
  { label: 'Заказы', value: '0', change: 'Данные появятся после подключения API', icon: ShoppingCart, color: 'bg-primary-75 text-primary-500' },
  { label: 'Выручка', value: '0 ₽', change: 'Оплаченные заказы за период', icon: RussianRuble, color: 'bg-success-75 text-success-500' },
  { label: 'Товары', value: '0', change: 'Активные позиции каталога', icon: Package, color: 'bg-blue-light-50 text-blue-light-500' },
  { label: 'Обращения', value: '0', change: 'Новые заявки клиентов', icon: MessageSquare, color: 'bg-warning-50 text-warning-500' },
]

const orders = [
  { number: '—', customer: 'Заказы появятся после подключения API', total: '—', status: 'Ожидание данных' },
]
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Добро пожаловать" title="Обзор магазина">
      <template #actions><UiButton>Добавить товар</UiButton></template>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <UiCard v-for="metric in metrics" :key="metric.label">
        <div class="flex items-start justify-between">
          <p class="text-sm font-medium text-gray-500">{{ metric.label }}</p>
          <span class="grid h-10 w-10 place-items-center rounded-lg" :class="metric.color"><component :is="metric.icon" :size="20" :stroke-width="2" /></span>
        </div>
        <p class="mt-5 text-3xl font-bold tracking-tight text-gray-900">{{ metric.value }}</p>
        <p class="mt-2 text-xs text-gray-400">{{ metric.change }}</p>
      </UiCard>
    </div>

    <div class="mt-6 grid gap-6 dashboard-sales-grid">
      <UiCard class="sm:p-6">
        <div class="flex items-start justify-between">
          <div><h2 class="text-base font-semibold text-gray-700">Динамика продаж</h2><p class="mt-1 text-sm text-gray-400">За последние 30 дней</p></div>
          <UiButton variant="secondary" size="sm">30 дней <ChevronDown :size="16" /></UiButton>
        </div>
        <div class="mt-8 flex h-56 items-end gap-2 border-b border-gray-100 px-2 pb-1">
          <span v-for="height in [22, 38, 29, 47, 36, 58, 45, 72, 60, 82, 68, 92]" :key="height" class="flex-1 rounded-t-md bg-primary-100" :style="{ height: `${height}%` }" />
        </div>
        <div class="mt-3 flex justify-between text-xs text-gray-400"><span>01 авг.</span><span>15 авг.</span><span>Сегодня</span></div>
      </UiCard>

      <UiCard class="sm:p-6">
        <h2 class="text-base font-semibold text-gray-700">Быстрые действия</h2>
        <div class="mt-5 space-y-3">
          <UiButton class="w-full justify-start text-left" variant="secondary"><Upload :size="18" class="text-primary-500" /><span>Импортировать товары</span><ArrowRight :size="18" class="ml-auto text-primary-500" /></UiButton>
          <UiButton class="w-full justify-start text-left" variant="secondary"><FilePlus2 :size="18" class="text-primary-500" /><span>Создать категорию</span><ArrowRight :size="18" class="ml-auto text-primary-500" /></UiButton>
          <UiButton class="w-full justify-start text-left" variant="secondary"><CircleHelp :size="18" class="text-primary-500" /><span>Посмотреть обращения</span><ArrowRight :size="18" class="ml-auto text-primary-500" /></UiButton>
        </div>
      </UiCard>
    </div>

    <UiCard class="mt-6 overflow-hidden" :padded="false">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-5 sm:px-6"><div><h2 class="text-base font-semibold text-gray-700">Последние заказы</h2><p class="mt-1 text-sm text-gray-400">Новые заказы из интернет-магазина</p></div><UiButton variant="ghost" size="sm">Все заказы</UiButton></div>
      <UiTable table-class="admin-table-orders" label="Последние заказы"><thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-6 py-3">Номер</th><th class="px-6 py-3">Клиент</th><th class="px-6 py-3">Сумма</th><th class="px-6 py-3">Статус</th></tr></thead><tbody><tr v-for="order in orders" :key="order.number" class="border-t border-gray-100 text-gray-600"><td class="px-6 py-4 font-medium">{{ order.number }}</td><td class="px-6 py-4">{{ order.customer }}</td><td class="px-6 py-4">{{ order.total }}</td><td class="px-6 py-4"><UiBadge>{{ order.status }}</UiBadge></td></tr></tbody></UiTable>
    </UiCard>
  </section>
</template>
