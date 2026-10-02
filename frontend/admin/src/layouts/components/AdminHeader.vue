<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminNavigation from './AdminNavigation.vue'
import AdminNotifications from './AdminNotifications.vue'
import AdminUserMenu from './AdminUserMenu.vue'

const compactOpen = ref(false)
const openPopup = ref<string | null>(null)
const route = useRoute()
function updatePopup(id: string, open: boolean): void {
  if (open) openPopup.value = id
  else if (openPopup.value === id) openPopup.value = null
}
watch(
  () => route.fullPath,
  () => {
    openPopup.value = null
  },
)
</script>

<template>
  <header class="sticky top-0 z-30 bg-white admin-header">
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
        <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
          <AdminNotifications
            :open="openPopup === 'notifications'"
            @update:open="updatePopup('notifications', $event)"
          />
          <AdminUserMenu
            :open="openPopup === 'user'"
            @update:open="updatePopup('user', $event)"
          />
        </div>
      </div>
      <AdminNavigation
        v-model:open-id="openPopup"
        @compact-open="compactOpen = $event"
      />
    </div>
  </header>
</template>
