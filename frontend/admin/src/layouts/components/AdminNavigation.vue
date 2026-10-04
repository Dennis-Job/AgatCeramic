<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Menu } from '@lucide/vue'
import UiButton from '../../components/ui/UiButton.vue'
import UiDialog from '../../components/ui/UiDialog.vue'
import UiPopover from '../../components/ui/UiPopover.vue'
import AdminNavigationLinks from './AdminNavigationLinks.vue'
import { useAuthStore } from '../../stores/auth'
import { isNavigationLinkActive, visibleNavigation } from '../navigation'

const emit = defineEmits<{ 'compact-open': [open: boolean] }>()
const openId = defineModel<string | null>('openId', { default: null })
const auth = useAuthStore()
const route = useRoute()
const compact = ref(false)
const sections = computed(() => visibleNavigation(auth.hasPermission))
const activeSection = computed(() =>
  sections.value.find((section) =>
    section.link
      ? isNavigationLinkActive(section.link, route)
      : section.groups?.some((group) =>
          group.links.some((link) => isNavigationLinkActive(link, route)),
        ),
  ),
)
const compactGroups = computed(() =>
  sections.value.flatMap((section) =>
    section.link
      ? [{ label: '', links: [section.link] }]
      : (section.groups ?? []),
  ),
)
let media: MediaQueryList | null = null
let previousMainInert = false

function updatePopup(id: string, open: boolean): void {
  if (open) openId.value = id
  else if (openId.value === id) openId.value = null
}
function resize(): void {
  openId.value = null
  compact.value = !media?.matches
}
function columns(count: number): Record<string, string> {
  return {
    '--admin-navigation-columns': String(count),
    '--ui-popover-width': `calc(var(--admin-popover-column-width) * ${count} + var(--admin-spacing-6) * ${count + 1})`,
  }
}
watch(sections, () => {
  if (
    openId.value &&
    !['compact', 'notifications', 'user'].includes(openId.value) &&
    !sections.value.some((section) => section.id === openId.value)
  )
    openId.value = null
})
watch(
  () => openId.value === 'compact',
  (open) => {
    emit('compact-open', open)
    const main = document.getElementById('admin-main')
    if (main) {
      if (open) {
        previousMainInert = main.inert
        main.inert = true
      } else main.inert = previousMainInert
    }
  },
)
onMounted(() => {
  media = window.matchMedia('(min-width: 1024px)')
  compact.value = !media.matches
  media.addEventListener('change', resize)
})
onBeforeUnmount(() => {
  media?.removeEventListener('change', resize)
  if (openId.value === 'compact') {
    const main = document.getElementById('admin-main')
    if (main) main.inert = previousMainInert
  }
})
</script>

<template>
  <div class="admin-navigation">
    <nav
      v-if="!compact"
      class="flex items-stretch gap-6"
      aria-label="Основная навигация"
    >
      <template v-for="section in sections" :key="section.id">
        <RouterLink
          v-if="section.link"
          :to="section.link.to"
          class="admin-nav-trigger"
          :class="{ 'is-active': activeSection?.id === section.id }"
          :aria-current="activeSection?.id === section.id ? 'page' : undefined"
          >{{ section.label }}</RouterLink
        >
        <UiPopover
          v-else
          :id="`admin-navigation-${section.id}-panel`"
          :open="openId === section.id"
          :label="`${section.label} — подразделы`"
          boundary-selector=".admin-header-inner"
          panel-class="admin-navigation-panel"
          :style="columns(section.groups?.length ?? 1)"
          @update:open="updatePopup(section.id, $event)"
        >
          <template #trigger="{ trigger, open }">
            <button
              v-bind="trigger"
              type="button"
              class="admin-nav-trigger"
              :class="{
                'is-active': activeSection?.id === section.id,
                'is-open': open,
              }"
            >
              {{ section.label }}
            </button>
          </template>
          <template #default="{ close }">
            <AdminNavigationLinks
              :groups="section.groups ?? []"
              label="Подразделы"
              @select="close()"
            />
          </template>
        </UiPopover>
      </template>
    </nav>
    <div
      v-else
      class="admin-navigation-compact flex min-w-0 items-center gap-3"
      :inert="openId === 'compact'"
    >
      <button
        type="button"
        class="admin-header-icon"
        aria-label="Открыть меню"
        :aria-expanded="openId === 'compact'"
        aria-controls="admin-navigation-panel"
        @click="updatePopup('compact', openId !== 'compact')"
      >
        <Menu :size="24" :stroke-width="1.5" aria-hidden="true" />
      </button>
    </div>
    <UiDialog
      :open="openId === 'compact'"
      labelledby="admin-navigation-title"
      data-testid="navigation-backdrop"
      overlay-class="admin-navigation-dialog-overlay z-40 flex items-start justify-center px-4 py-2 sm:px-6"
      panel-class="admin-navigation-dialog-panel"
      @close="updatePopup('compact', false)"
    >
      <div id="admin-navigation-panel" class="admin-navigation-panel-compact">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 id="admin-navigation-title" class="text-base font-bold">
            Разделы панели
          </h2>
          <UiButton
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Закрыть меню"
            @click="updatePopup('compact', false)"
            >Закрыть</UiButton
          >
        </div>
        <AdminNavigationLinks
          :groups="compactGroups"
          label="Основная навигация"
          @select="updatePopup('compact', false)"
        />
      </div>
    </UiDialog>
  </div>
</template>
