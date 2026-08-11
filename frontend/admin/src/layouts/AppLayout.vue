<script setup lang="ts">
import { ref } from 'vue'
import { Bell, Menu, Search } from '@lucide/vue'
import AppSidebar from '../components/AppSidebar.vue'

const isSidebarOpen = ref(false)
</script>

<template>
  <div class="min-h-screen bg-[#f7f9fc] text-[#1d2939]">
    <AppSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <main class="min-h-screen lg:pl-[290px]">
      <header class="sticky top-0 z-20 flex h-[76px] items-center gap-4 border-b border-[#e4e7ec] bg-white px-4 sm:px-6 lg:px-8">
        <button class="grid h-10 w-10 place-items-center rounded-lg border border-[#e4e7ec] text-[#475467] lg:hidden" aria-label="Открыть меню" @click="isSidebarOpen = true">
          <Menu :size="20" />
        </button>
        <label class="hidden max-w-[360px] flex-1 items-center gap-3 rounded-lg border border-[#e4e7ec] bg-[#f9fafb] px-3.5 py-2.5 md:flex">
          <Search :size="18" class="shrink-0 text-[#98a2b3]" />
          <input class="w-full bg-transparent text-sm outline-none placeholder:text-[#98a2b3]" placeholder="Поиск…" type="search" />
        </label>
        <div class="ml-auto flex items-center gap-3">
          <button class="grid h-10 w-10 place-items-center rounded-full text-[#667085] hover:bg-[#f2f4f7]" aria-label="Уведомления"><Bell :size="20" /></button>
          <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-[#344054]">Администратор</p>
            <p class="text-xs text-[#98a2b3]">admin@agatceramic.ru</p>
          </div>
          <div class="grid h-10 w-10 place-items-center rounded-full bg-[#7f56d9] text-sm font-bold text-white">А</div>
        </div>
      </header>
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
