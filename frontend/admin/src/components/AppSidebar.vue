<script setup lang="ts">
import { computed } from 'vue'
import { Boxes, FileText, LayoutDashboard, Settings, ShieldCheck, ShoppingCart, UsersRound, X } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
defineProps<{ isOpen: boolean }>()
defineEmits<{ close: [] }>()

const auth = useAuthStore()

const primaryNavigation = [
  { label: 'Обзор', to: '/', icon: LayoutDashboard },
  { label: 'Каталог', to: '/products', icon: Boxes },
  { label: 'Заказы', to: '/orders', icon: ShoppingCart },
]

const secondaryNavigation = [
  { label: 'Сотрудники', to: '/employees', icon: UsersRound, requiredPermission: 'admin-users.view' },
  { label: 'Роли', to: '/roles', icon: ShieldCheck, requiredPermission: 'roles.view' },
  { label: 'Контент', to: '/content', icon: FileText },
  { label: 'Настройки', to: '/settings', icon: Settings },
]

const visibleSecondaryNavigation = computed(() => secondaryNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-30 bg-[#101828]/40 lg:hidden" @click="$emit('close')" />
  <aside
    class="fixed inset-y-0 left-0 z-40 flex w-[290px] -translate-x-full flex-col border-r border-[#e4e7ec] bg-white px-4 py-6 transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': isOpen }"
  >
    <div class="mb-9 flex items-center justify-between px-2">
      <RouterLink class="flex items-center gap-3" to="/" @click="$emit('close')">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#7f56d9] text-lg font-bold text-white">А</span>
        <span class="text-lg font-bold tracking-tight text-[#1d2939]">AgatCeramic</span>
      </RouterLink>
      <button class="grid h-9 w-9 place-items-center rounded-lg text-[#667085] hover:bg-[#f2f4f7] lg:hidden" aria-label="Закрыть меню" @click="$emit('close')"><X :size="20" /></button>
    </div>

    <nav class="space-y-1">
      <RouterLink
        v-for="item in primaryNavigation"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[#475467] transition hover:bg-[#f9fafb]"
        active-class="!bg-[#f4f3ff] !text-[#6941c6]"
        @click="$emit('close')"
      >
        <component :is="item.icon" :size="19" :stroke-width="1.8" />{{ item.label }}
      </RouterLink>
    </nav>

    <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#98a2b3]">Управление</p>
    <nav class="space-y-1">
      <RouterLink
        v-for="item in visibleSecondaryNavigation"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-[#475467] transition hover:bg-[#f9fafb]"
        active-class="!bg-[#f4f3ff] !text-[#6941c6]"
        @click="$emit('close')"
      >
        <component :is="item.icon" :size="19" :stroke-width="1.8" />{{ item.label }}
      </RouterLink>
    </nav>

    <div class="mt-auto rounded-xl bg-[#f9fafb] p-4">
      <p class="text-sm font-semibold text-[#344054]">Нужна помощь?</p>
      <p class="mt-1 text-xs leading-5 text-[#667085]">Документация и поддержка проекта.</p>
      <button class="mt-3 text-xs font-semibold text-[#6941c6]">Открыть справку</button>
    </div>
  </aside>
</template>
