<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminNavigation from './AdminNavigation.vue'
import AdminNotifications from './AdminNotifications.vue'
import AdminUserMenu from './AdminUserMenu.vue'

const compactOpen = ref(false)
const openPopup = ref<string | null>(null)
const route = useRoute()
const emit = defineEmits<{ 'condensed-change': [condensed: boolean] }>()
const header = ref<HTMLElement | null>(null)
const condensed = ref(false)
let collapseThreshold = 0

function updateScroll(): void {
  const next = window.scrollY > collapseThreshold
  if (next === condensed.value) return
  condensed.value = next
  emit('condensed-change', next)
}
onMounted(() => {
  collapseThreshold = header.value
    ? parseFloat(
        getComputedStyle(header.value).getPropertyValue(
          '--admin-shell-navigation-height',
        ),
      )
    : 0
  window.addEventListener('scroll', updateScroll, { passive: true })
  updateScroll()
})
onBeforeUnmount(() => window.removeEventListener('scroll', updateScroll))
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
  <header
    ref="header"
    class="sticky top-0 z-30 bg-white admin-header"
    :class="{ 'is-condensed': condensed }"
  >
    <div class="admin-header-inner">
      <RouterLink
        to="/"
        class="admin-brand shrink-0"
        aria-label="AgatCeramic — главная"
        :inert="compactOpen"
      >
        <span class="font-bold text-gray-500"
          >Agat<span class="text-primary-500">Ceramic</span></span
        >
        <span class="block text-xs text-gray-500">Админ-панель</span>
      </RouterLink>
      <AdminNavigation
        v-model:open-id="openPopup"
        @compact-open="compactOpen = $event"
      />
      <div
        class="admin-header-actions flex min-w-0 items-center gap-2 sm:gap-3"
        :inert="compactOpen"
      >
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
  </header>
  <!-- Preserve document geometry while the sticky surface loses its second row. -->
  <div class="admin-header-spacer" aria-hidden="true" />
</template>
