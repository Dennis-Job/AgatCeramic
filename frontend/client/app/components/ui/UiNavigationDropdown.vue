<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'

defineProps<{ label: string; current?: boolean }>()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const open = ref(false)
const position = ref<Record<string, string>>({})
const id = useId()
const route = useRoute()
const headerHeight = useSiteHeaderHeight()
let observer: ResizeObserver | undefined

function close(restoreFocus = false) {
  if (!open.value) return
  open.value = false
  if (panel.value?.matches(':popover-open')) panel.value.hidePopover()
  if (restoreFocus) trigger.value?.focus({ preventScroll: true })
}

function updatePosition() {
  if (!open.value || !trigger.value || !root.value) return
  const bounds = trigger.value.getBoundingClientRect()
  if (
    !root.value.getClientRects().length ||
    bounds.top < headerHeight.value - 1 ||
    bounds.bottom > window.innerHeight - 44
  ) {
    close()
    return
  }
  const margin = 12
  const viewport = document.documentElement.clientWidth
  const width = Math.min(320, viewport - margin * 2)
  position.value = {
    left: `${Math.max(margin, Math.min(bounds.left, viewport - width - margin))}px`,
    top: `${bounds.bottom}px`,
    width: `${width}px`,
    maxHeight: `${window.innerHeight - bounds.bottom - margin}px`,
  }
}

async function toggle() {
  if (open.value) return close()
  open.value = true
  await nextTick()
  if (!open.value) return
  updatePosition()
  if (open.value) panel.value?.showPopover()
}

function outside(event: Event) {
  if (event.target instanceof Node && !root.value?.contains(event.target))
    close()
}

function escape(event: KeyboardEvent) {
  if (!open.value) return
  event.preventDefault()
  event.stopPropagation()
  close(true)
}

function revealLink(event: FocusEvent) {
  if (
    event.target instanceof HTMLElement &&
    panel.value?.contains(event.target)
  )
    event.target.scrollIntoView({
      block: 'nearest',
      inline: 'nearest',
      behavior: 'instant',
    })
}

watch(
  () => route.fullPath,
  () => close(),
)
watch(headerHeight, updatePosition)
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  document.addEventListener('focusin', outside)
  window.addEventListener('resize', updatePosition)
  window.addEventListener('scroll', updatePosition, true)
  observer = new ResizeObserver(updatePosition)
  if (root.value) observer.observe(root.value)
})
onBeforeUnmount(() => {
  close()
  observer?.disconnect()
  document.removeEventListener('pointerdown', outside)
  document.removeEventListener('focusin', outside)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', updatePosition, true)
})
</script>

<template>
  <div ref="root" class="ui-navigation-dropdown" @keydown.esc="escape">
    <button
      :id="`${id}-trigger`"
      ref="trigger"
      type="button"
      class="ui-navigation-dropdown__trigger"
      :class="{ 'ui-navigation-dropdown__trigger--current': current }"
      :aria-label="label"
      :aria-expanded="open"
      :aria-controls="`${id}-panel`"
      @click="toggle"
    >
      <span>{{ label }}</span>
      <slot name="suffix" />
      <ChevronDown :size="14" :stroke-width="1.6" aria-hidden="true" />
    </button>
    <div
      :id="`${id}-panel`"
      ref="panel"
      class="ui-navigation-dropdown__panel ui-submenu-popover"
      popover="manual"
      :aria-labelledby="`${id}-trigger`"
      :inert="!open"
      :aria-hidden="!open"
      :style="position"
      @focusin="revealLink"
    >
      <slot :close="close" />
    </div>
  </div>
</template>

<style scoped>
.ui-navigation-dropdown {
  min-width: 0;
}
.ui-navigation-dropdown__trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  max-width: 100%;
  border: 0;
  border-bottom: 1px solid transparent;
  padding: 12px 0;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: 13px;
  line-height: 1.5;
  text-align: left;
  overflow-wrap: anywhere;
}
.ui-navigation-dropdown__trigger > span:first-child {
  min-width: 0;
}
.ui-navigation-dropdown__trigger:hover,
.ui-navigation-dropdown__trigger[aria-expanded='true'],
.ui-navigation-dropdown__trigger--current {
  color: var(--color-ink);
}
.ui-navigation-dropdown__trigger--current {
  border-bottom-color: var(--color-ink);
}
.ui-navigation-dropdown__trigger svg {
  flex-shrink: 0;
  transition: transform var(--motion-disclosure-duration) var(--ease-out);
}
.ui-navigation-dropdown__trigger[aria-expanded='true'] svg {
  transform: rotate(180deg);
}
.ui-navigation-dropdown__panel {
  position: fixed;
  inset: auto;
  box-sizing: border-box;
  margin: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid var(--color-line);
  padding: 8px;
  background: var(--color-bg-soft);
  color: var(--color-ink);
  box-shadow: 0 12px 28px var(--color-shadow-soft);
}
.ui-navigation-dropdown__panel :deep(a) {
  display: block;
  min-height: 44px;
  padding: 12px;
  font-size: 13px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.ui-navigation-dropdown__panel :deep(a:hover),
.ui-navigation-dropdown__panel :deep(a[aria-current]) {
  background: var(--color-beige);
}
.ui-navigation-dropdown__panel :deep(a[aria-current]) {
  text-decoration: underline;
  text-underline-offset: 4px;
}
.ui-navigation-dropdown__panel :deep(a:focus-visible) {
  outline-offset: -3px;
}
</style>
