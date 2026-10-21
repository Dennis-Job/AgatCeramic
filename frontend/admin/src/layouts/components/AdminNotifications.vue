<script setup lang="ts">
import { Bell } from '@lucide/vue'
import { watch } from 'vue'
import UiPopover from '../../components/ui/UiPopover.vue'
import UiEmptyState from '../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../components/ui/UiLoadingState.vue'
import UiAlert from '../../components/ui/UiAlert.vue'
import UiButton from '../../components/ui/UiButton.vue'
import UiBadge from '../../components/ui/UiBadge.vue'
import { useContactNotifications } from '../../features/contacts/composables/useContactNotifications'
import { formatPhone } from '../../utils/phone'
const open = defineModel<boolean>('open', { default: false })
const { contacts, total, loading, error, canView, refresh } =
  useContactNotifications()
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
        <UiBadge
          v-if="total"
          tone="primary"
          class="absolute -right-2 -top-1"
          aria-hidden="true"
          >{{ total > 99 ? '99+' : total }}</UiBadge
        >
      </button>
      <span v-if="total" id="admin-notifications-count" class="sr-only"
        >Новых заявок на звонок: {{ total }}</span
      >
    </template>
    <h2 class="text-base font-bold text-gray-500">Уведомления</h2>
    <template v-if="canView">
      <p class="mt-2 text-xs text-gray-500">
        Новые заявки на звонок · {{ total }}. Остаются здесь до смены статуса
        обращения.
      </p>
      <UiLoadingState
        v-if="loading && !contacts.length"
        label="Загрузка уведомлений…"
      />
      <div v-else-if="error" class="mt-4 space-y-3">
        <UiAlert>{{ error }}</UiAlert>
        <UiButton variant="secondary" @click.stop="retry">Повторить</UiButton>
      </div>
      <UiEmptyState
        v-else-if="!contacts.length"
        label="Новых заявок на звонок нет"
      />
      <ul v-else class="mt-4 divide-y divide-gray-100" :aria-busy="loading">
        <li v-for="item in contacts" :key="item.id">
          <RouterLink
            :to="{ path: '/contacts', query: { contact: item.id } }"
            class="admin-focus block break-words rounded-lg py-3 text-sm text-gray-500 hover:bg-gray-50"
            @click="open = false"
          >
            <span class="block font-semibold"
              >Обратный звонок #{{ item.id }}</span
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
        to="/contacts"
        class="admin-focus mt-4 inline-block text-sm font-semibold text-primary-500"
        @click="open = false"
        >Все обращения</RouterLink
      >
    </template>
    <UiEmptyState v-else label="Новых уведомлений нет" />
  </UiPopover>
</template>
