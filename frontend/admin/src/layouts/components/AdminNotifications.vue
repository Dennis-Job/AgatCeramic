<script setup lang="ts">
import { Bell } from '@lucide/vue'
import { toRefs, watch } from 'vue'
import UiPopover from '../../components/ui/UiPopover.vue'
import UiEmptyState from '../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../components/ui/UiLoadingState.vue'
import UiAlert from '../../components/ui/UiAlert.vue'
import UiButton from '../../components/ui/UiButton.vue'
import UiNotificationCount from '../../components/ui/UiNotificationCount.vue'
import type { AdminNotificationState } from '../../features/notifications/composables/useAdminNotifications'
import { formatPhone } from '../../utils/phone'
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ state: AdminNotificationState }>()
const {
  contacts,
  contactsTotal,
  ordersTotal,
  total,
  loading,
  error,
  canView,
  canViewContacts,
  canViewOrders,
} = toRefs(props.state)
const refresh = () => props.state.refresh()
const contactLabels = {
  callback: 'Обратный звонок',
  email: 'Email',
  partner: 'Партнёрство',
}
watch(open, (value) => {
  if (value) void refresh()
})

function retry(event: MouseEvent) {
  const target = event.currentTarget as HTMLElement
  target
    .closest<HTMLElement>('[data-floating-popover]')
    ?.focus({ preventScroll: true })
  void refresh()
}
</script>

<template>
  <UiPopover
    id="admin-notifications-panel"
    v-model:open="open"
    align="end"
    label="Уведомления"
  >
    <template #trigger="{ trigger }">
      <button
        v-bind="trigger"
        type="button"
        class="admin-header-icon relative"
        aria-label="Уведомления"
        :aria-describedby="total ? 'admin-notifications-count' : undefined"
      >
        <Bell
          :size="24"
          fill="currentColor"
          :stroke-width="1.5"
          aria-hidden="true"
        />
        <UiNotificationCount
          :count="total"
          :max="99"
          class="absolute -right-2 -top-1"
        />
      </button>
      <span v-if="total" id="admin-notifications-count" class="sr-only"
        >Новых уведомлений: {{ total }}</span
      >
    </template>
    <h2 class="text-base font-bold text-gray-500">Уведомления</h2>
    <template v-if="canView">
      <p class="mt-2 text-xs text-gray-500">
        <span v-if="canViewContacts"
          >Новые обращения · {{ contactsTotal }}.
        </span>
        <span v-if="canViewOrders">Новые заказы · {{ ordersTotal }}. </span>
        Остаются здесь до смены статуса.
      </p>
      <UiLoadingState
        v-if="loading && !contacts.length && !ordersTotal"
        label="Загрузка уведомлений…"
      />
      <div v-if="error" class="mt-4 space-y-3">
        <UiAlert>{{ error }}</UiAlert>
        <UiButton variant="secondary" @click.stop="retry">Повторить</UiButton>
      </div>
      <UiEmptyState
        v-if="!loading && !error && !total"
        label="Новых уведомлений нет"
      />
      <ul
        v-if="contacts.length"
        class="mt-4 divide-y divide-gray-100"
        :aria-busy="loading"
      >
        <li v-for="item in contacts" :key="item.id">
          <RouterLink
            :to="{ path: '/contacts', query: { contact: item.id } }"
            class="admin-focus block break-words rounded-lg py-3 text-sm text-gray-500 hover:bg-gray-50"
            @click="open = false"
          >
            <span class="block font-semibold"
              >{{ contactLabels[item.type] }} #{{ item.id }}</span
            >
            <span class="block">{{ item.contact.name || 'Без имени' }}</span>
            <span v-if="item.contact.phone" class="block">{{
              formatPhone(item.contact.phone)
            }}</span>
            <time class="block text-xs" :datetime="item.created_at">{{
              new Date(item.created_at).toLocaleString('ru-RU')
            }}</time>
          </RouterLink>
        </li>
      </ul>
      <RouterLink
        v-if="canViewOrders && ordersTotal"
        to="/orders"
        class="admin-focus mt-4 block rounded-lg py-3 text-sm font-semibold text-primary-500"
        @click="open = false"
        >Новые заказы · {{ ordersTotal }}</RouterLink
      >
      <RouterLink
        v-if="canViewContacts"
        to="/contacts"
        class="admin-focus mt-4 inline-block text-sm font-semibold text-primary-500"
        @click="open = false"
        >Все обращения</RouterLink
      >
    </template>
    <UiEmptyState v-else label="Новых уведомлений нет" />
  </UiPopover>
</template>
