<script setup lang="ts">
import { ref } from 'vue'
import UiInput from '../../components/ui/UiInput.vue'
import AdminNavigation from './AdminNavigation.vue'
import AdminNotifications from './AdminNotifications.vue'
import AdminUserMenu from './AdminUserMenu.vue'

const compactOpen = ref(false)
</script>

<template>
  <header
    class="sticky top-0 z-30 border-b border-gray-200 bg-white admin-header"
  >
    <div class="admin-header-inner">
      <div
        class="admin-header-top flex min-w-0 items-center gap-3"
        :inert="compactOpen"
      >
        <RouterLink
          to="/"
          class="admin-brand shrink-0"
          aria-label="AgatCeramic — главная"
        >
          <span class="font-bold text-gray-800"
            >Agat<span class="text-primary-500">Ceramic</span></span
          >
          <span class="block text-xs text-gray-500">Админ-панель</span>
        </RouterLink>
        <UiInput
          class="hidden admin-header-search min-w-0 flex-1 md:flex"
          searchable
          placeholder="Поиск…"
          type="search"
          aria-label="Поиск"
        />
        <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
          <AdminNotifications />
          <AdminUserMenu />
        </div>
      </div>
      <AdminNavigation @compact-open="compactOpen = $event" />
    </div>
  </header>
</template>
