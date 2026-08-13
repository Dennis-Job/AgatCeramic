<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, LogOut, Menu } from '@lucide/vue'
import BaseInput from '../components/BaseInput.vue'
import AppSidebar from '../components/AppSidebar.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const isSidebarOpen = ref(false)
const initial = computed(() => auth.user?.name.slice(0, 1).toUpperCase() ?? 'А')

async function signOut(): Promise<void> {
  await auth.logout()
  await router.replace('/login')
}
</script>

<template>
  <div class="min-h-screen bg-page text-gray-800">
    <AppSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <main class="min-h-screen admin-layout-main">
      <header class="sticky top-0 z-20 flex admin-header items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
        <button class="grid h-10 w-10 place-items-center rounded-lg border border-gray-200 text-gray-600 lg:hidden" aria-label="Открыть меню" @click="isSidebarOpen = true">
          <Menu :size="20" />
        </button>
        <BaseInput class="hidden admin-header-search flex-1 md:flex" searchable placeholder="Поиск…" type="search" />
        <div class="ml-auto flex items-center gap-3">
          <button class="grid h-10 w-10 place-items-center rounded-full text-gray-500 hover:bg-gray-50" aria-label="Уведомления"><Bell :size="20" /></button>
          <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-gray-700">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-400">{{ auth.user?.email }}</p>
          </div>
          <button class="grid h-10 w-10 cursor-pointer place-items-center rounded-full bg-primary-500 text-sm font-bold text-white hover:bg-primary-600" aria-label="Мой профиль" @click="router.push({ name: 'profile' })">{{ initial }}</button>
          <button class="grid h-10 w-10 place-items-center rounded-full text-gray-500 hover:bg-gray-50" aria-label="Выйти" @click="signOut"><LogOut :size="19" /></button>
        </div>
      </header>
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
