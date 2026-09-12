<script setup lang="ts">
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import type { Order } from '../types/order.types'

defineProps<{ orders: Order[]; selectedId?: number; loading: boolean; date: (value: string | null) => string; amount: (value: string | null) => string; statusName: (code: string) => string; paymentName: (code: string) => string }>()
defineEmits<{ select: [order: Order] }>()
</script>

<template>
  <UiLoadingState v-if="loading" label="Загрузка заказов…" />
  <UiEmptyState v-else-if="!orders.length" label="Заказы не найдены." />
  <template v-else>
    <div class="divide-y divide-gray-100 md:hidden xl:block">
      <article v-for="order in orders" :key="order.id" :class="selectedId === order.id ? 'bg-primary-50' : ''" class="p-4">
        <button type="button" class="w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50" :aria-label="`Открыть заказ ${order.order_number}`" :aria-current="selectedId === order.id ? 'true' : undefined" @click="$emit('select', order)">
          <div class="flex flex-wrap items-start justify-between gap-2"><div><span class="block break-all font-semibold text-gray-800">{{ order.order_number }}</span><span class="mt-1 block text-xs text-gray-500">{{ date(order.created_at) }}</span></div><strong class="text-gray-700">{{ amount(order.total_amount) }}</strong></div>
          <div class="mt-3 flex flex-wrap gap-2"><UiBadge tone="primary">{{ statusName(order.status) }}</UiBadge><UiBadge tone="neutral">{{ paymentName(order.payment_status) }}</UiBadge></div>
          <p class="mt-3 break-words text-sm font-medium text-gray-700">{{ order.customer.name }}</p><p class="break-all text-xs text-gray-500">{{ order.customer.phone }}</p>
        </button>
      </article>
    </div>
    <div class="xl:hidden">
    <UiTable class="hidden md:block" min-width="min-w-[680px]" label="Список заказов"><thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-5 py-3">Заказ</th><th class="px-5 py-3">Клиент</th><th class="px-5 py-3">Статус</th><th class="px-5 py-3">Сумма</th></tr></thead><tbody><tr v-for="order in orders" :key="order.id" class="border-t border-gray-100 text-gray-600" :class="selectedId === order.id ? 'bg-primary-50' : ''"><td class="px-5 py-4"><button type="button" class="-m-2 rounded-lg p-2 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" :aria-label="`Открыть заказ ${order.order_number}`" :aria-current="selectedId === order.id ? 'true' : undefined" @click="$emit('select', order)"><span class="block font-semibold text-gray-800">{{ order.order_number }}</span><span class="mt-1 block text-xs text-gray-500">{{ date(order.created_at) }}</span></button></td><td class="px-5 py-4"><p class="font-medium text-gray-700">{{ order.customer.name }}</p><p class="mt-1 text-xs text-gray-500">{{ order.customer.phone }}</p></td><td class="px-5 py-4"><UiBadge tone="primary">{{ statusName(order.status) }}</UiBadge><p class="mt-2 text-xs text-gray-500">{{ paymentName(order.payment_status) }}</p></td><td class="px-5 py-4 font-semibold text-gray-700">{{ amount(order.total_amount) }}</td></tr></tbody></UiTable>
    </div>
  </template>
</template>
