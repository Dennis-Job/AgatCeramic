<script setup lang="ts">
import { MessageSquare } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { Order, OrderComment, OrderHistory, OrderStatus, PaymentForm } from '../types/order.types'

defineProps<{ selected: Order | null; statuses: OrderStatus[]; histories: OrderHistory[]; comments: OrderComment[]; detailLoading: boolean; saving: boolean; actionError: string; canManage: boolean; canManageStatus: boolean; canManagePayment: boolean; date: (value: string | null) => string; amount: (value: string | null) => string; statusName: (code: string) => string; paymentOptions: { label: string; value: string }[] }>()
const paymentForm = defineModel<PaymentForm>('paymentForm', { required: true })
const commentBody = defineModel<string>('commentBody', { required: true })
defineEmits<{ changeStatus: []; savePayment: []; submitComment: [] }>()
</script>

<template>
  <aside class="min-w-0 rounded-xl border border-gray-200 bg-white shadow-card">
    <UiAlert v-if="actionError && !selected" class="m-5 mb-0">{{ actionError }}</UiAlert>
    <UiLoadingState v-if="detailLoading" label="Загрузка деталей заказа…" />
    <div v-else-if="!selected" class="p-8 text-sm text-gray-500" role="status">Выберите заказ в списке, чтобы открыть рабочее место менеджера.</div>
    <div v-else class="space-y-6 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-medium text-gray-500">Заказ</p><h2 class="mt-1 break-all text-xl font-bold text-gray-900">{{ selected.order_number }}</h2></div><strong class="text-lg text-gray-800">{{ amount(selected.total_amount) }}</strong></div>
      <UiAlert v-if="actionError">{{ actionError }}</UiAlert>
      <section><h3 class="text-sm font-semibold text-gray-900">Клиент и доставка</h3><dl class="mt-3 space-y-2 text-sm text-gray-600"><div><dt class="text-xs text-gray-500">Клиент</dt><dd class="break-words">{{ selected.customer.name }} · {{ selected.customer.phone }}</dd><dd v-if="selected.customer.email" class="break-all">{{ selected.customer.email }}</dd></div><div><dt class="text-xs text-gray-500">Адрес</dt><dd class="whitespace-pre-line break-words">{{ selected.delivery_address }}</dd></div><div v-if="selected.customer_comment"><dt class="text-xs text-gray-500">Комментарий клиента</dt><dd class="whitespace-pre-line break-words">{{ selected.customer_comment }}</dd></div></dl></section>
      <section><h3 class="text-sm font-semibold text-gray-900">Состав заказа</h3><ul class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-200"><li v-for="item in selected.items" :key="`${item.product_id}-${item.product_name}`" class="flex flex-wrap justify-between gap-3 p-3 text-sm"><span class="min-w-0"><b class="break-words text-gray-800">{{ item.product_name }}</b><small class="mt-1 block break-all text-gray-500">{{ item.product_sku ?? 'Без SKU' }} · {{ item.quantity }} шт.</small></span><strong class="whitespace-nowrap text-gray-700">{{ amount(item.line_total) }}</strong></li></ul></section>
      <section v-if="canManageStatus" class="rounded-lg border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-900">Статус заказа</h3><div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"><UiSelect v-model="selected.status" :options="statuses.map(item => ({ label: item.name, value: item.code }))" accessible-name="Новый статус заказа" :disabled="saving" /><UiButton :loading="saving" :disabled="saving" @click="$emit('changeStatus')">Сохранить</UiButton></div></section>
      <section v-if="canManagePayment" class="rounded-lg border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-900">Оплата</h3><div class="mt-3 grid gap-3"><UiSelect v-model="paymentForm.payment_status" :options="paymentOptions" accessible-name="Статус оплаты" :disabled="saving" /><template v-if="!['not_paid', 'pending'].includes(paymentForm.payment_status)"><UiInput v-model="paymentForm.payment_amount" type="number" min="0.01" step="0.01" placeholder="Сумма" aria-label="Сумма оплаты" :disabled="saving" /><UiInput v-model="paymentForm.payment_method" placeholder="Способ оплаты" aria-label="Способ оплаты" :disabled="saving" /><UiInput v-model="paymentForm.payment_reference" placeholder="Номер документа (необязательно)" aria-label="Номер документа" :disabled="saving" /></template><UiButton :loading="saving" :disabled="saving" @click="$emit('savePayment')">Сохранить оплату</UiButton></div></section>
      <section><h3 class="text-sm font-semibold text-gray-900">История статусов</h3><ol class="mt-3 space-y-2 border-l border-gray-200 pl-4 text-sm"><li v-for="entry in histories" :key="entry.id"><p class="break-words text-gray-700">{{ statusName(entry.from_status) }} → <b>{{ statusName(entry.to_status) }}</b></p><p class="text-xs text-gray-500">{{ entry.actor?.name ?? 'Система' }} · {{ date(entry.occurred_at) }}</p></li><li v-if="!histories.length" class="text-gray-500">Изменений статуса пока нет.</li></ol></section>
      <section><h3 class="text-sm font-semibold text-gray-900">Внутренние комментарии</h3><div class="mt-3 space-y-3"><article v-for="comment in comments" :key="comment.id" class="rounded-lg bg-gray-25 p-3 text-sm"><p class="whitespace-pre-line break-words text-gray-700">{{ comment.body }}</p><p class="mt-2 text-xs text-gray-500">{{ comment.author?.name ?? 'Система' }} · {{ date(comment.created_at) }}</p></article><p v-if="!comments.length" class="text-sm text-gray-500">Комментариев пока нет.</p></div><form v-if="canManage" class="mt-3" @submit.prevent="$emit('submitComment')"><label class="sr-only" for="order-comment">Новый внутренний комментарий</label><UiTextarea id="order-comment" v-model="commentBody" rows="3" placeholder="Добавить внутренний комментарий" :disabled="saving" /><UiButton class="mt-2" type="submit" variant="secondary" :disabled="saving || !commentBody.trim()"><MessageSquare :size="16" aria-hidden="true" />Добавить</UiButton></form></section>
    </div>
  </aside>
</template>
