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
  <div class="min-h-screen bg-[#f7f9fc] text-[#1d2939]">
    <AppSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <main class="min-h-screen lg:pl-[290px]">
      <header class="sticky top-0 z-20 flex h-[76px] items-center gap-4 border-b border-[#e4e7ec] bg-white px-4 sm:px-6 lg:px-8">
        <button class="grid h-10 w-10 place-items-center rounded-lg border border-[#e4e7ec] text-[#475467] lg:hidden" aria-label="Открыть меню" @click="isSidebarOpen = true">
          <Menu :size="20" />
        </button>
        <BaseInput class="hidden max-w-[360px] flex-1 md:flex" searchable placeholder="Поиск…" type="search" />
        <div class="ml-auto flex items-center gap-3">
          <button class="grid h-10 w-10 place-items-center rounded-full text-[#667085] hover:bg-[#f2f4f7]" aria-label="Уведомления"><Bell :size="20" /></button>
          <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-[#344054]">{{ auth.user?.name }}</p>
            <p class="text-xs text-[#98a2b3]">{{ auth.user?.email }}</p>
          </div>
          <div class="grid h-10 w-10 place-items-center rounded-full bg-[#7f56d9] text-sm font-bold text-white">{{ initial }}</div>
          <button class="grid h-10 w-10 place-items-center rounded-full text-[#667085] hover:bg-[#f2f4f7]" aria-label="Выйти" @click="signOut"><LogOut :size="19" /></button>
        </div>
      </header>
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
