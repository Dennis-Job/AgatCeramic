<script setup lang="ts">
import { computed } from 'vue'
import { BadgeCheck, FolderTree, Layers3, ListFilter } from '@lucide/vue'
import { Package, FileText, KeyRound, LayoutDashboard, ScrollText, Settings, ShieldCheck, ShoppingCart, UsersRound, X } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
defineProps<{ isOpen: boolean }>()
defineEmits<{ close: [] }>()

const auth = useAuthStore()

const productManagementNavigation = [
  { label: 'Товары', to: '/products', icon: Package, requiredPermission: 'catalog.manage' },
  { label: 'Категории', to: '/categories', icon: FolderTree, requiredPermission: 'catalog.manage' },
  { label: 'Бренды', to: '/brands', icon: BadgeCheck, requiredPermission: 'catalog.manage' },
  { label: 'Группы характеристик', to: '/attribute-groups', icon: Layers3, requiredPermission: 'catalog.manage' },
  { label: 'Характеристики', to: '/attributes', icon: ListFilter, requiredPermission: 'catalog.manage' },
]

const primaryNavigation = [
  { label: 'Обзор', to: '/', icon: LayoutDashboard },
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
const visiblePrimaryNavigation = computed(() => primaryNavigation)
const visibleProductManagementNavigation = computed(() => productManagementNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" @click="$emit('close')" />
  <aside
    class="fixed inset-y-0 left-0 z-40 flex admin-sidebar -translate-x-full flex-col border-r border-gray-200 bg-white px-4 py-6 transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': isOpen }"
  >
    <div class="mb-9 flex items-center justify-between px-2">
      <RouterLink class="flex items-center gap-3" to="/" @click="$emit('close')">
        <span class="grid h-10 w-10 place-items-center">
          <img src="/public/logo.svg" alt="Logo">
        </span>
        <span>
          <p class="text-2xl leading-6 font-bold tracking-tight text-gray-800">Agat<span class="text-gray-400">Ceramic</span></p>
          <p class="text-xs text-end">Админ-панель</p>
        </span>
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

    <template v-if="visibleProductManagementNavigation.length">
      <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление товарами</p>
      <nav class="space-y-1">
        <RouterLink
          v-for="item in visibleProductManagementNavigation"
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
  </aside>
</template>
