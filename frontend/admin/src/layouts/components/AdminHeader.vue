<script setup lang="ts">
import { Menu } from '@lucide/vue'
import UiInput from '../../components/ui/UiInput.vue'
import AdminNotifications from './AdminNotifications.vue'
import AdminUserMenu from './AdminUserMenu.vue'

defineProps<{ isNavigationOpen: boolean }>()
defineEmits<{ 'open-navigation': [] }>()
</script>

<template>
  <header class="sticky top-0 z-20 flex admin-header items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
    <button
      type="button"
      class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-gray-200 text-gray-600 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50 lg:hidden"
      aria-label="Открыть меню"
      aria-controls="admin-sidebar"
      :aria-expanded="isNavigationOpen"
      @click="$emit('open-navigation')"
    ><Menu :size="20" aria-hidden="true" /></button>
    <UiInput class="hidden admin-header-search flex-1 md:flex" searchable placeholder="Поиск…" type="search" aria-label="Поиск" />
    <div class="ml-auto flex min-w-0 items-center gap-3">
      <AdminNotifications />
      <AdminUserMenu />
    </div>
  </header>
</template>
