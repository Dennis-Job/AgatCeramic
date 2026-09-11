<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { BadgeCheck, FolderTree, Layers3, ListFilter } from '@lucide/vue'
import { Package, FileText, KeyRound, LayoutDashboard, MessageSquareMore, PanelsTopLeft, ScrollText, Settings, ShieldCheck, ShoppingCart, UsersRound, X } from '@lucide/vue'
import { useAuthStore } from '../../stores/auth'

const props = defineProps<{ isOpen: boolean }>()
const emit = defineEmits<{ close: [] }>()
const sidebar = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const auth = useAuthStore()
let opener: HTMLElement | null = null

const productManagementNavigation = [
  { label: 'Товары', to: '/products', icon: Package, requiredPermission: 'catalog.manage' },
  { label: 'Категории', to: '/categories', icon: FolderTree, requiredPermission: 'catalog.manage' },
  { label: 'Бренды', to: '/brands', icon: BadgeCheck, requiredPermission: 'catalog.manage' },
  { label: 'Группы характеристик', to: '/attribute-groups', icon: Layers3, requiredPermission: 'catalog.manage' },
  { label: 'Характеристики', to: '/attributes', icon: ListFilter, requiredPermission: 'catalog.manage' },
]
const primaryNavigation = [
  { label: 'Обзор', to: '/', icon: LayoutDashboard },
  { label: 'Заказы', to: '/orders', icon: ShoppingCart, requiredPermission: 'orders.view' },
  { label: 'Обращения', to: '/contacts', icon: MessageSquareMore, requiredPermission: 'contacts.view' },
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
const developmentNavigation = [
  { label: 'UI-kit · временно', to: '/ui-kit', icon: PanelsTopLeft },
]

const visibleEmployeeNavigation = computed(() => employeeNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
const visiblePrimaryNavigation = computed(() => primaryNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))
const visibleProductManagementNavigation = computed(() => productManagementNavigation.filter((item) => !item.requiredPermission || auth.hasPermission(item.requiredPermission)))

function close(): void { emit('close') }
const focusableSelector = [
  'button:not([disabled])',
  '[href]',
  'input:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

function focusableElements(): HTMLElement[] {
  return Array.from(sidebar.value?.querySelectorAll<HTMLElement>(focusableSelector) ?? [])
    .filter(element => element.tabIndex >= 0 && element.getClientRects().length > 0)
}

function handleKeydown(event: KeyboardEvent): void {
  if (!props.isOpen) return
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }
  if (event.key !== 'Tab') return
  const elements = focusableElements()
  if (!elements.length) return
  const first = elements[0]
  const last = elements[elements.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
watch(() => props.isOpen, async (open) => {
  if (open) {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
    await nextTick()
    closeButton.value?.focus()
  } else if (opener?.isConnected) {
    await nextTick()
    opener.focus()
    opener = null
  }
})
onBeforeUnmount(() => { if (opener?.isConnected) opener.focus() })
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" aria-hidden="true" @click="close" />
  <aside
    id="admin-sidebar"
    ref="sidebar"
    class="fixed inset-y-0 left-0 z-40 flex admin-sidebar -translate-x-full flex-col overflow-y-auto border-r border-gray-200 bg-white px-4 py-6 transition-transform duration-200 lg:translate-x-0 lg:overflow-visible"
    :class="{ 'translate-x-0': isOpen }"
    @keydown="handleKeydown"
  >
    <div class="mb-9 flex items-center justify-between px-2">
      <RouterLink class="flex min-w-0 items-center gap-3" to="/" @click="close">
        <span class="grid h-10 w-10 shrink-0 place-items-center"><img src="/logo.svg" alt="AgatCeramic" class="logo-primary"></span>
        <span class="min-w-0"><span class="block truncate text-2xl leading-6 font-bold tracking-tight text-gray-800">Agat<span class="text-gray-400">Ceramic</span></span><span class="block text-end text-xs">Админ-панель</span></span>
      </RouterLink>
      <button ref="closeButton" type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50 lg:hidden" aria-label="Закрыть меню" @click="close"><X :size="20" aria-hidden="true" /></button>
    </div>

    <nav class="space-y-1" aria-label="Основная навигация">
      <RouterLink v-for="item in visiblePrimaryNavigation" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25" active-class="!bg-primary-50 !text-primary-600" @click="close"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true" />{{ item.label }}</RouterLink>
    </nav>
    <template v-if="visibleProductManagementNavigation.length"><p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление товарами</p><nav class="space-y-1" aria-label="Управление товарами"><RouterLink v-for="item in visibleProductManagementNavigation" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25" active-class="!bg-primary-50 !text-primary-600" @click="close"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true" />{{ item.label }}</RouterLink></nav></template>
    <template v-if="visibleEmployeeNavigation.length"><p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление сотрудниками</p><nav class="space-y-1" aria-label="Управление сотрудниками"><RouterLink v-for="item in visibleEmployeeNavigation" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25" active-class="!bg-primary-50 !text-primary-600" @click="close"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true" />{{ item.label }}</RouterLink></nav></template>
    <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Управление сайтом</p>
    <nav class="space-y-1" aria-label="Управление сайтом"><RouterLink v-for="item in siteManagementNavigation" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25" active-class="!bg-primary-50 !text-primary-600" @click="close"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true" />{{ item.label }}</RouterLink></nav>
    <p class="mb-2 mt-8 px-3 text-xs font-semibold uppercase admin-nav-heading text-gray-400">Разработка</p>
    <nav class="space-y-1" aria-label="Разработка"><RouterLink v-for="item in developmentNavigation" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-25" active-class="!bg-primary-50 !text-primary-600" @click="close"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true" />{{ item.label }}</RouterLink></nav>
  </aside>
</template>
