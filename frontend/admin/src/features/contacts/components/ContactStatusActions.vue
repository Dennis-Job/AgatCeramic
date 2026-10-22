<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { ArrowRight, LockKeyhole } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import type { ContactStatus } from '../types/contact.types'
import { contactStatusTone } from '../utils/contactStatusTone'

const props = defineProps<{
  currentStatus?: ContactStatus
  allowedStatuses: ContactStatus[]
  statuses: ContactStatus[]
  ready: boolean
  canManage: boolean
  disabled: boolean
}>()
const emit = defineEmits<{ changeStatus: [status: string]; retry: [] }>()
const pending = ref<ContactStatus | null>(null)
const root = ref<HTMLElement | null>(null)
watch(
  () => props.currentStatus?.code,
  () => {
    pending.value = null
  },
)

function label(status: ContactStatus) {
  if (status.code === 'processing')
    return props.currentStatus?.code === 'new'
      ? 'Начать обработку'
      : 'Вернуть в обработку'
  if (status.code === 'awaiting_client') return 'Ожидать клиента'
  if (status.code === 'completed') return 'Завершить'
  if (status.code === 'rejected') return 'Отклонить обращение'
  return status.name
}
async function request(status: ContactStatus) {
  if (props.disabled || !props.ready || !props.canManage) return
  if (status.is_terminal) {
    pending.value = status
    await nextTick()
    root.value?.querySelector<HTMLElement>('[data-confirm-status]')?.focus()
  } else emit('changeStatus', status.code)
}
async function cancel() {
  const code = pending.value?.code
  pending.value = null
  await nextTick()
  root.value?.querySelector<HTMLElement>(`[data-status="${code}"]`)?.focus()
}
function confirm() {
  if (!pending.value || props.disabled || !props.ready || !props.canManage)
    return
  emit('changeStatus', pending.value.code)
}
</script>

<template>
  <section
    ref="root"
    class="contact-status-actions"
    aria-labelledby="contact-actions-title"
  >
    <h3 id="contact-actions-title" class="text-base font-bold text-gray-500">
      Обработка обращения
    </h3>
    <p v-if="currentStatus?.is_terminal" class="mt-3 text-sm text-gray-500">
      Обращение
      {{ currentStatus.code === 'rejected' ? 'отклонено' : 'завершено' }}.
      Статус больше нельзя изменить.
    </p>
    <p v-else-if="!canManage" class="mt-3 text-sm text-gray-500">
      Для изменения статуса требуется право управления обращениями.
    </p>
    <div v-else-if="!ready" class="mt-3 space-y-3">
      <p class="text-sm text-gray-500">
        Действия со статусом временно недоступны.
      </p>
      <UiButton variant="secondary" :disabled="disabled" @click="emit('retry')"
        >Повторить загрузку</UiButton
      >
    </div>
    <div
      v-else-if="pending"
      class="mt-3 space-y-3"
      role="group"
      aria-label="Подтверждение изменения статуса"
    >
      <p class="text-sm font-semibold">
        {{ pending.code === 'rejected' ? 'Отклонить' : 'Завершить' }} обращение?
      </p>
      <p class="text-sm text-gray-500">
        После подтверждения статус нельзя изменить.
      </p>
      <div class="flex flex-wrap gap-2">
        <UiButton
          data-confirm-status
          :variant="pending.code === 'rejected' ? 'danger' : 'primary'"
          :disabled="disabled"
          @click="confirm"
          >{{
            pending.code === 'rejected'
              ? 'Подтвердить отклонение'
              : 'Подтвердить завершение'
          }}</UiButton
        >
        <UiButton variant="secondary" :disabled="disabled" @click="cancel"
          >Отмена</UiButton
        >
      </div>
    </div>
    <div v-else class="mt-3 space-y-4">
      <div class="contact-status-step">
        <span
          >Сейчас:
          <UiBadge
            class="shrink-0 whitespace-nowrap"
            :tone="contactStatusTone(currentStatus?.code ?? '')"
            >{{ currentStatus?.name }}</UiBadge
          ></span
        >
        <template
          v-if="
            currentStatus?.code === 'new' &&
            allowedStatuses.some((item) => item.code === 'processing')
          "
        >
          <ArrowRight :size="18" aria-hidden="true" />
          <span>Следующий шаг: <b>В обработке</b></span>
        </template>
      </div>
      <div class="grid gap-2" :aria-busy="disabled">
        <UiButton
          v-for="(action, index) in allowedStatuses"
          :key="action.code"
          :data-status="action.code"
          :variant="
            action.code === 'rejected'
              ? 'primary-ghost'
              : index === 0
                ? 'primary'
                : 'secondary'
          "
          :disabled="disabled"
          @click="request(action)"
          >{{ label(action) }}</UiButton
        >
      </div>
      <div v-if="currentStatus?.code === 'new'" class="contact-status-help">
        <p>После начала обработки доступны</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <UiButton
            v-for="action in statuses.filter(
              (item) =>
                ['awaiting_client', 'completed'].includes(item.code) &&
                !allowedStatuses.some((allowed) => allowed.code === item.code),
            )"
            :key="action.code"
            variant="secondary"
            disabled
            :aria-describedby="'contact-status-explanation'"
          >
            <LockKeyhole :size="14" aria-hidden="true" />{{
              action.code === 'completed' ? 'Завершить' : action.name
            }}
          </UiButton>
        </div>
        <p id="contact-status-explanation" class="mt-3">
          Сначала начните обработку. Из нового обращения эти статусы недоступны.
        </p>
        <p class="mt-2">
          Из обработки можно сразу завершить — ожидание клиента необязательно.
        </p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.contact-status-actions {
  padding: var(--admin-spacing-4);
  border: var(--admin-border-width) solid var(--admin-color-gray-200);
  border-radius: var(--admin-radius-xl);
}
.contact-status-step {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--admin-spacing-2);
  padding: var(--admin-spacing-3);
  background: var(--admin-color-primary-50);
  border-radius: var(--admin-radius-lg);
  font-size: 0.875rem;
}
.contact-status-help {
  padding: var(--admin-spacing-3);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-color-gray-50);
  color: var(--admin-color-gray-500);
  font-size: 0.8125rem;
  line-height: 1.6;
}
</style>
