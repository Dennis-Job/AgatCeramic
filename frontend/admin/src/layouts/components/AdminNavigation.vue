<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '../../components/ui/UiButton.vue'
import UiDialog from '../../components/ui/UiDialog.vue'
import AdminNavigationLinks from './AdminNavigationLinks.vue'
import { useAuthStore } from '../../stores/auth'
import { isNavigationLinkActive, visibleNavigation } from '../navigation'

const emit = defineEmits<{ 'compact-open': [open: boolean] }>()
const auth = useAuthStore()
const route = useRoute()
const root = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const openId = ref<string | null>(null)
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
const openSection = computed(() =>
  sections.value.find((section) => section.id === openId.value),
)
const panelGroups = computed(() =>
  openId.value === 'compact'
    ? sections.value.flatMap((section) =>
        section.link
          ? [{ label: '', links: [section.link] }]
          : (section.groups ?? []),
      )
    : (openSection.value?.groups ?? []),
)
let opener: HTMLElement | null = null
let media: MediaQueryList | null = null
let previousMainInert = false

function focusables(): HTMLElement[] {
  return Array.from(
    panel.value?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href]',
    ) ?? [],
  )
}

async function close(restoreFocus = true): Promise<void> {
  if (!openId.value) return
  openId.value = null
  const target = opener
  opener = null
  await nextTick()
  if (restoreFocus && target?.isConnected) target.focus()
}

async function toggle(id: string, event: Event): Promise<void> {
  if (openId.value === id) {
    await close()
    return
  }
  opener = event.currentTarget as HTMLElement
  openId.value = id
  await nextTick()
  focusables()[0]?.focus()
}

function handleKeydown(event: KeyboardEvent): void {
  if (!openId.value || compact.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    void close()
    return
  }
  const elements = focusables()
  const index = elements.indexOf(document.activeElement as HTMLElement)
  if (
    ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) &&
    panel.value?.contains(event.target as Node)
  ) {
    event.preventDefault()
    const next =
      event.key === 'Home'
        ? 0
        : event.key === 'End'
          ? elements.length - 1
          : (index + (event.key === 'ArrowDown' ? 1 : -1) + elements.length) %
            elements.length
    elements[next]?.focus()
  }
}

function outside(event: MouseEvent): void {
  if (
    !compact.value &&
    openId.value &&
    !root.value?.contains(event.target as Node)
  )
    void close()
}
function focusOutside(event: FocusEvent): void {
  if (
    !compact.value &&
    openId.value &&
    !root.value?.contains(event.target as Node)
  )
    void close(false)
}
function resize(): void {
  void close(false)
  compact.value = !media?.matches
}

watch(
  () => route.fullPath,
  () => {
    void close()
  },
)
watch(sections, () => {
  if (openId.value && openId.value !== 'compact' && !openSection.value)
    void close()
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
  document.addEventListener('click', outside)
  document.addEventListener('focusin', focusOutside)
  document.addEventListener('keydown', handleKeydown)
})
onBeforeUnmount(() => {
  media?.removeEventListener('change', resize)
  document.removeEventListener('click', outside)
  document.removeEventListener('focusin', focusOutside)
  document.removeEventListener('keydown', handleKeydown)
  if (openId.value === 'compact') {
    const main = document.getElementById('admin-main')
    if (main) main.inert = previousMainInert
  }
})
</script>

<template>
  <div ref="root" class="admin-navigation">
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
        <button
          v-else
          type="button"
          class="admin-nav-trigger"
          :class="{
            'is-active': activeSection?.id === section.id,
            'is-open': openId === section.id,
          }"
          :aria-expanded="openId === section.id"
          aria-controls="admin-navigation-panel"
          @click="toggle(section.id, $event)"
          @keydown.down.prevent="toggle(section.id, $event)"
        >
          {{ section.label }}
        </button>
      </template>
    </nav>
    <div
      v-else
      class="admin-navigation-compact flex min-w-0 items-center gap-3"
      :inert="openId === 'compact'"
    >
      <UiButton
        type="button"
        variant="ghost"
        size="sm"
        aria-label="Открыть меню"
        :aria-expanded="openId === 'compact'"
        aria-controls="admin-navigation-panel"
        @click="toggle('compact', $event)"
        >Меню</UiButton
      >
      <span class="truncate text-sm text-gray-500">{{
        activeSection?.label ?? route.meta.title
      }}</span>
    </div>
    <UiDialog
      :open="openId === 'compact'"
      labelledby="admin-navigation-title"
      data-testid="navigation-backdrop"
      overlay-class="admin-navigation-dialog-overlay z-40 flex items-start justify-center px-4 py-2 sm:px-6"
      panel-class="admin-navigation-dialog-panel"
      @close="close()"
    >
      <div id="admin-navigation-panel">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 id="admin-navigation-title" class="text-base font-bold">
            Разделы панели
          </h2>
          <UiButton
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Закрыть меню"
            @click="close()"
            >Закрыть</UiButton
          >
        </div>
        <AdminNavigationLinks
          :groups="panelGroups"
          label="Основная навигация"
          @select="close()"
        />
      </div>
    </UiDialog>
    <section
      v-if="openId && !compact"
      id="admin-navigation-panel"
      ref="panel"
      class="admin-navigation-panel"
    >
      <AdminNavigationLinks
        :groups="panelGroups"
        label="Подразделы"
        @select="close()"
      />
    </section>
  </div>
</template>
