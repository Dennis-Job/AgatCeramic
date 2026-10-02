<script setup lang="ts">
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import type { Order } from '../types/order.types'

defineProps<{
  orders: Order[]
  selectedId?: number
  loading: boolean
  date: (value: string | null) => string
  amount: (value: string | null) => string
  statusName: (code: string) => string
  paymentName: (code: string) => string
}>()
defineEmits<{ select: [order: Order] }>()
</script>

<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка заказов…"
  />
  <UiEmptyState
    v-else-if="!orders.length"
    class="admin-container"
    label="Заказы не найдены."
  />
  <template v-else>
    <div class="admin-container divide-y divide-gray-100 md:hidden">
      <article
        v-for="order in orders"
        :key="order.id"
        :class="selectedId === order.id ? 'bg-primary-50' : ''"
        class="p-4"
      >
        <button
          type="button"
          class="w-full rounded-lg text-left admin-focus"
          :aria-label="`Открыть заказ ${order.order_number}`"
          :aria-current="selectedId === order.id ? 'true' : undefined"
          @click="$emit('select', order)"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <span class="block break-all font-semibold text-gray-800">{{
                order.order_number
              }}</span
              ><time
                class="mt-1 block text-xs text-gray-500"
                :datetime="order.created_at"
                >{{ date(order.created_at) }}</time
              >
            </div>
            <strong class="text-gray-700">{{
              amount(order.total_amount)
            }}</strong>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <UiBadge tone="primary">{{ statusName(order.status) }}</UiBadge
            ><UiBadge tone="neutral">{{
              paymentName(order.payment_status)
            }}</UiBadge>
          </div>
          <p class="mt-3 break-words text-sm font-medium text-gray-700">
            {{ order.customer.name }}
          </p>
          <p class="break-all text-xs text-gray-500">
            {{ order.customer.phone }}
          </p>
        </button>
      </article>
    </div>
    <div class="min-w-0">
      <UiTable
        class="hidden md:block"
        min-width="min-w-[760px]"
        table-class="seller-table"
        sticky-header
        label="Список заказов"
        ><thead class="bg-gray-25 text-xs font-medium text-gray-500">
          <tr>
            <th scope="col" class="w-56 px-5 py-3">Заказ</th>
            <th scope="col" class="px-5 py-3">Клиент</th>
            <th scope="col" class="w-40 px-5 py-3">Статус</th>
            <th scope="col" class="w-44 text-right px-5 py-3">Сумма</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="order in orders"
            :key="order.id"
            class="border-t border-gray-100 text-gray-600"
            :class="selectedId === order.id ? 'bg-primary-50' : ''"
          >
            <td class="px-5 py-4">
              <button
                type="button"
                class="-m-2 rounded-lg p-2 text-left admin-focus"
                :aria-label="`Открыть заказ ${order.order_number}`"
                :aria-current="selectedId === order.id ? 'true' : undefined"
                @click="$emit('select', order)"
              >
                <span class="block font-semibold text-gray-800">{{
                  order.order_number
                }}</span
                ><time
                  class="mt-1 block text-xs text-gray-500"
                  :datetime="order.created_at"
                  >{{ date(order.created_at) }}</time
                >
              </button>
            </td>
            <td class="px-5 py-4">
              <p class="font-medium text-gray-700">{{ order.customer.name }}</p>
              <p class="mt-1 text-xs text-gray-500">
                {{ order.customer.phone }}
              </p>
            </td>
            <td class="px-5 py-4">
              <UiBadge tone="primary">{{ statusName(order.status) }}</UiBadge>
              <p class="mt-2 text-xs text-gray-500">
                {{ paymentName(order.payment_status) }}
              </p>
            </td>
            <td class="px-5 py-4 text-right font-semibold text-gray-700">
              {{ amount(order.total_amount) }}
            </td>
          </tr>
        </tbody></UiTable
      >
    </div>
  </template>
</template>
