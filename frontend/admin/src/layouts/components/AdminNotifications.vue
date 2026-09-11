<script setup lang="ts">
import { Bell } from '@lucide/vue'
</script>

<template>
  <button
    type="button"
    class="grid h-10 w-10 place-items-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
    aria-label="Уведомления"
    aria-describedby="admin-notifications-status"
  >
    <Bell :size="20" aria-hidden="true" />
    <span id="admin-notifications-status" class="sr-only">Новых уведомлений нет</span>
  </button>
</template>
