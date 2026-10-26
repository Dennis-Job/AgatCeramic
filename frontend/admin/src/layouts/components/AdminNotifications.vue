<script setup lang="ts">
import { Bell } from '@lucide/vue'
import UiNotificationCount from '../../components/ui/UiNotificationCount.vue'
import type { AdminNotificationState } from '../../features/notifications/composables/useAdminNotifications'
defineProps<{ state: AdminNotificationState }>()
</script>

<template>
  <div>
    <RouterLink
      to="/notifications"
      class="admin-header-icon admin-focus relative"
      aria-label="Уведомления"
      :aria-describedby="state.total ? 'admin-notifications-count' : undefined"
    >
      <Bell
        :size="24"
        fill="currentColor"
        :stroke-width="1.5"
        aria-hidden="true"
      />
      <UiNotificationCount
        :count="state.total"
        :max="99"
        class="absolute -right-2 -top-1"
      />
    </RouterLink>
    <span v-if="state.total" id="admin-notifications-count" class="sr-only"
      >Новых уведомлений: {{ state.total }}</span
    >
  </div>
</template>
