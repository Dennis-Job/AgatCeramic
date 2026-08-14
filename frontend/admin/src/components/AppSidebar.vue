<script setup lang="ts">
import { computed } from 'vue'
import { BadgeCheck, FolderTree, Layers3, ListFilter } from '@lucide/vue'
import { Boxes, FileText, KeyRound, LayoutDashboard, ScrollText, Settings, ShieldCheck, ShoppingCart, UsersRound, X } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
defineProps<{ isOpen: boolean }>()
defineEmits<{ close: [] }>()

const auth = useAuthStore()

const primaryNavigation = [
  { label: 'Бренды', to: '/brands', icon: BadgeCheck, requiredPermission: 'catalog.manage' },
  { label: 'Характеристики', to: '/attributes', icon: ListFilter, requiredPermission: 'catalog.manage' },
  { label: 'Группы характеристик', to: '/attribute-groups', icon: Layers3, requiredPermission: 'catalog.manage' },
  { label: 'Категории', to: '/categories', icon: FolderTree, requiredPermission: 'catalog.manage' },
  { label: 'Обзор', to: '/', icon: LayoutDashboard },
  { label: 'Каталог', to: '/products', icon: Boxes },
  { label: 'Заказы', to: '/orders', icon: ShoppingCart },
]

const employeeNavigation = [
  { label: 'Сотрудники', to: '/employees', icon: UsersRound, requiredPermission: 'admin-users.view' },
  { label: 'Роли', to: '/roles', icon: ShieldCheck, requiredPermission: 'roles.view' },
  { label: 'Права', to: '/permissions', icon: KeyRound, requiredPermission: 'permissions.view' },
  { label: 'Журнал аудита', to: '/audit-log', icon: ScrollText, requiredPermission: 'audit-log.view' },
]

const siteManagementNavigation = [
  { label: 'Контент', to: '/content', icon: FileText },
  { label: 'Настройки', to: '/settings', icon: Settings },
]

const visibleEmployeeNavigation = computed(() => employeeNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
const visiblePrimaryNavigation = computed(() => primaryNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" @click="$emit('close')" />
  <aside
    class="fixed inset-y-0 left-0 z-40 flex admin-sidebar -translate-x-full flex-col border-r border-gray-200 bg-white px-4 py-6 transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': isOpen }"
  >
    <div class="mb-9 flex items-center justify-between px-2">
      <RouterLink class="flex items-center gap-3" to="/" @click="$emit('close')">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary-500 text-lg font-bold text-white">А</span>
        <span class="text-lg font-bold tracking-tight text-gray-800">AgatCeramic</span>
      </RouterLink>
      <button class="grid h-9 w-9 place-items-center rounded-lg text-gray-500 hover:bg-gray-50 lg:hidden" aria-label="Закрыть меню" @click="$emit('close')"><X :size="20" /></button>
    </div>

    <nav class="space-y-1">
      <RouterLink
        v-for="item in visiblePrimaryNavigation"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25"
        active-class="!bg-primary-50 !text-primary-600"
        @click="$emit('close')"
      >
        <component :is="item.icon" :size="19" :stroke-width="1.8" />{{ item.label }}
      </RouterLink>
    </nav>

    <template v-if="visibleEmployeeNavigation.length">
      <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление сотрудниками</p>
      <nav class="space-y-1">
        <RouterLink
          v-for="item in visibleEmployeeNavigation"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25"
          active-class="!bg-primary-50 !text-primary-600"
          @click="$emit('close')"
        >
          <component :is="item.icon" :size="19" :stroke-width="1.8" />{{ item.label }}
        </RouterLink>
      </nav>
    </template>

    <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление сайтом</p>
    <nav class="space-y-1">
      <RouterLink
        v-for="item in siteManagementNavigation"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25"
        active-class="!bg-primary-50 !text-primary-600"
        @click="$emit('close')"
      >
        <component :is="item.icon" :size="19" :stroke-width="1.8" />{{ item.label }}
      </RouterLink>
    </nav>

    <div class="mt-auto rounded-xl bg-gray-25 p-4">
      <p class="text-sm font-semibold text-gray-700">Нужна помощь?</p>
      <p class="mt-1 text-xs leading-5 text-gray-500">Документация и поддержка проекта.</p>
      <button class="mt-3 text-xs font-semibold text-primary-600">Открыть справку</button>
    </div>
  </aside>
</template>
