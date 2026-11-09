<script setup lang="ts">
import { MessageSquare } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import { orderStatusTone } from '../utils/orderStatusTone'
import { paymentStatusTone } from '../utils/paymentStatusTone'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { formatPhone } from '../../../utils/phone'
import type {
  Order,
  OrderComment,
  OrderHistory,
  OrderStatus,
  PaymentForm,
} from '../types/order.types'

defineProps<{
  selected: Order | null
  statuses: OrderStatus[]
  histories: OrderHistory[]
  comments: OrderComment[]
  detailLoading: boolean
  saving: boolean
  actionError: string
  canManage: boolean
  canManageStatus: boolean
  canManagePayment: boolean
  canAssign: boolean
  assigneeOptions: { label: string; value: string }[]
  date: (value: string | null) => string
  amount: (value: string | null) => string
  statusName: (code: string) => string
  paymentName: (code: string) => string
  paymentOptions: { label: string; value: string }[]
}>()
const paymentForm = defineModel<PaymentForm>('paymentForm', { required: true })
const commentBody = defineModel<string>('commentBody', { required: true })
defineEmits<{
  changeStatus: []
  savePayment: []
  submitComment: []
  retry: []
  changeAssignee: [value: string]
}>()
</script>

<template>
  <div class="min-w-0" role="region" aria-label="Детали выбранной записи">
    <UiNotification v-if="actionError && !selected">{{
      actionError
    }}</UiNotification>
    <UiLoadingState v-if="detailLoading" label="Загрузка деталей заказа…" />
    <div v-else-if="!selected" class="p-8 text-sm text-gray-500" role="status">
      <template v-if="actionError"
        ><p>Не удалось открыть заказ.</p>
        <UiButton class="mt-3" variant="secondary" @click="$emit('retry')"
          >Повторить загрузку заказа</UiButton
        ></template
      >
      <template v-else>Выберите заказ в списке.</template>
    </div>
    <div v-else class="space-y-6">
      <UiNotification v-if="actionError">{{ actionError }}</UiNotification>
      <div
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500"
      >
        <div class="flex flex-wrap items-center gap-2">
          <UiBadge
            :tone="orderStatusTone(selected.status)"
            class="whitespace-nowrap"
            >{{ statusName(selected.status) }}</UiBadge
          ><UiBadge
            :tone="paymentStatusTone(selected.payment_status)"
            class="whitespace-nowrap"
            >{{ paymentName(selected.payment_status) }}</UiBadge
          >
        </div>
        <strong class="text-lg">{{ amount(selected.total_amount) }}</strong>
        <time class="w-full text-xs" :datetime="selected.created_at">{{
          date(selected.created_at)
        }}</time>
      </div>
      <section>
        <h3 class="text-sm font-semibold text-gray-500">Клиент и доставка</h3>
        <dl class="mt-3 space-y-2 text-sm text-gray-500">
          <div>
            <dt class="text-xs text-gray-500">Клиент</dt>
            <dd class="break-words">
              {{ selected.customer.name }}
              <a
                v-if="selected.customer.phone"
                class="mt-1 block rounded admin-focus"
                :href="`tel:${selected.customer.phone}`"
                >{{ formatPhone(selected.customer.phone) }}</a
              >
            </dd>
            <dd v-if="selected.customer.email" class="break-all">
              <a
                class="rounded admin-focus"
                :href="`mailto:${selected.customer.email}`"
                >{{ selected.customer.email }}</a
              >
            </dd>
          </div>
          <div>
            <dt class="text-xs text-gray-500">Адрес</dt>
            <dd class="whitespace-pre-line break-words">
              {{ selected.delivery_address }}
            </dd>
          </div>
          <div v-if="selected.customer_comment">
            <dt class="text-xs text-gray-500">Комментарий клиента</dt>
            <dd class="whitespace-pre-line break-words">
              {{ selected.customer_comment }}
            </dd>
          </div>
        </dl>
      </section>
      <section>
        <h3 class="text-sm font-semibold text-gray-500">Состав заказа</h3>
        <ul class="admin-panel--inset mt-3 divide-y divide-gray-100">
          <li
            v-for="item in selected.items"
            :key="`${item.product_id}-${item.product_name}`"
            class="flex flex-wrap justify-between gap-3 p-3 text-sm"
          >
            <span class="min-w-0"
              ><b class="break-words text-gray-500">{{ item.product_name }}</b
              ><small class="mt-1 block break-all text-gray-500"
                >{{ item.product_sku ?? 'Без SKU' }} ·
                {{ item.quantity }} шт.</small
              ></span
            ><strong class="whitespace-nowrap text-gray-500">{{
              amount(item.line_total)
            }}</strong>
          </li>
        </ul>
      </section>
      <section class="admin-panel--inset p-4">
        <h3 class="text-sm font-semibold text-gray-500">Ответственный</h3>
        <UiSelect
          v-if="canManage"
          class="mt-3"
          :model-value="selected.assignee ? String(selected.assignee.id) : ''"
          :options="assigneeOptions"
          accessible-name="Ответственный за заказ"
          teleport-menu
          :disabled="saving || !canAssign"
          @update:model-value="$emit('changeAssignee', $event)"
        />
        <p v-else class="mt-3 break-words text-sm text-gray-500">
          {{ selected.assignee?.name ?? 'Не назначен' }}
        </p>
      </section>
      <section v-if="canManageStatus" class="admin-panel--inset p-4">
        <h3 class="text-sm font-semibold text-gray-500">Статус заказа</h3>
        <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
          <UiSelect
            v-model="selected.status"
            :options="
              statuses
                .filter((item) => item.is_selectable !== false)
                .map((item) => ({ label: item.name, value: item.code }))
            "
            :placeholder="statusName(selected.status)"
            accessible-name="Новый статус заказа"
            :disabled="saving"
          /><UiButton
            :loading="saving"
            :disabled="
              saving ||
              statuses.some(
                (item) =>
                  item.code === selected?.status &&
                  item.is_selectable === false,
              )
            "
            @click="$emit('changeStatus')"
            >Сохранить</UiButton
          >
        </div>
      </section>
      <section v-if="canManagePayment" class="admin-panel--inset p-4">
        <h3 class="text-sm font-semibold text-gray-500">Оплата</h3>
        <div class="mt-3 space-y-3">
          <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
            <UiSelect
              v-model="paymentForm.payment_status"
              :options="paymentOptions"
              accessible-name="Статус оплаты"
              :disabled="saving"
            />
            <UiButton
              aria-label="Сохранить оплату"
              :loading="saving"
              :disabled="saving"
              @click="$emit('savePayment')"
              >Сохранить</UiButton
            >
          </div>
          <div
            v-if="!['not_paid', 'pending'].includes(paymentForm.payment_status)"
            class="grid gap-3"
          >
            <UiInput
              v-model="paymentForm.payment_amount"
              money
              min="0.01"
              step="0.01"
              placeholder="Сумма"
              aria-label="Сумма оплаты"
              :disabled="saving"
            /><UiInput
              v-model="paymentForm.payment_method"
              placeholder="Способ оплаты"
              aria-label="Способ оплаты"
              :disabled="saving"
            /><UiInput
              v-model="paymentForm.payment_reference"
              placeholder="Номер документа (необязательно)"
              aria-label="Номер документа"
              :disabled="saving"
            />
          </div>
        </div>
      </section>
      <section>
        <h3 class="text-sm font-semibold text-gray-500">История статусов</h3>
        <ol class="mt-3 space-y-2 border-l border-gray-200 pl-4 text-sm">
          <li v-for="entry in histories" :key="entry.id">
            <p class="break-words text-gray-500">
              {{ statusName(entry.from_status) }} →
              <b>{{ statusName(entry.to_status) }}</b>
            </p>
            <p class="text-xs text-gray-500">
              {{ entry.actor?.name ?? 'Система' }} ·
              {{ date(entry.occurred_at) }}
            </p>
          </li>
          <li v-if="!histories.length" class="text-gray-500">
            Изменений статуса пока нет.
          </li>
        </ol>
      </section>
      <section>
        <h3 class="text-sm font-semibold text-gray-500">
          Внутренние комментарии
        </h3>
        <div class="mt-3 space-y-3">
          <article
            v-for="comment in comments"
            :key="comment.id"
            class="admin-panel--inset p-3 text-sm"
          >
            <p class="whitespace-pre-line break-words text-gray-500">
              {{ comment.body }}
            </p>
            <p class="mt-2 text-xs text-gray-500">
              {{ comment.author?.name ?? 'Система' }} ·
              {{ date(comment.created_at) }}
            </p>
          </article>
          <p v-if="!comments.length" class="text-sm text-gray-500">
            Комментариев пока нет.
          </p>
        </div>
        <form
          v-if="canManage"
          class="mt-3"
          @submit.prevent="$emit('submitComment')"
        >
          <label class="sr-only" for="order-comment"
            >Новый внутренний комментарий</label
          ><UiTextarea
            id="order-comment"
            v-model="commentBody"
            rows="3"
            placeholder="Добавить внутренний комментарий"
            :disabled="saving"
          /><UiButton
            class="mt-2"
            type="submit"
            variant="secondary"
            :disabled="saving || !commentBody.trim()"
            ><MessageSquare :size="16" aria-hidden="true" />Добавить</UiButton
          >
        </form>
      </section>
    </div>
  </div>
</template>
