<script setup lang="ts">
import { vTooltip } from '../../components/ui/tooltip'
import { Bell } from '@lucide/vue'
import UiPopover from '../../components/ui/UiPopover.vue'
import UiEmptyState from '../../components/ui/UiEmptyState.vue'
const open = defineModel<boolean>('open', { default: false })
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
        v-tooltip="open ? false : undefined"
        v-bind="trigger"
        type="button"
        class="admin-header-icon"
        aria-label="Уведомления"
      >
        <Bell
          :size="24"
          fill="currentColor"
          :stroke-width="1.5"
          aria-hidden="true"
        />
      </button>
    </template>
    <h2 class="text-base font-bold text-gray-900">Уведомления</h2>
    <UiEmptyState label="Новых уведомлений нет" />
  </UiPopover>
</template>
