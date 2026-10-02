<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  useId,
  watch,
} from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    label: string
    id?: string
    align?: 'start' | 'end'
    panelClass?: string
    closeDelay?: number
  }>(),
  { id: undefined, align: 'start', panelClass: '', closeDelay: 180 },
)
const emit = defineEmits<{ 'update:open': [open: boolean] }>()
const generatedId = useId()
const panelId = computed(() => props.id ?? `ui-popover-${generatedId}`)
const root = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const offset = ref(0)
const offsetTop = ref<number>()
const maxHeight = ref<number>()
let closeTimer: ReturnType<typeof setTimeout> | undefined
let resizeObserver: ResizeObserver | undefined

function triggerElement(): HTMLElement | null {
  return root.value?.querySelector<HTMLElement>('[aria-controls]') ?? null
}
function hoverAvailable(): boolean {
  return (
    window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false
  )
}
function cancelClose(): void {
  clearTimeout(closeTimer)
  closeTimer = undefined
}
function pointerEnter(event: PointerEvent): void {
  if (event.pointerType !== 'mouse' || !hoverAvailable()) return
  cancelClose()
  emit('update:open', true)
}
function pointerLeave(event: PointerEvent): void {
  if (event.pointerType !== 'mouse' || !props.open) return
  cancelClose()
  closeTimer = setTimeout(() => {
    if (!root.value?.contains(document.activeElement))
      emit('update:open', false)
  }, props.closeDelay)
}
async function close(restoreFocus = false): Promise<void> {
  cancelClose()
  emit('update:open', false)
  if (restoreFocus) {
    await nextTick()
    triggerElement()?.focus()
  }
}
function focusables(): HTMLElement[] {
  return Array.from(
    panel.value?.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), [tabindex="0"]',
    ) ?? [],
  )
}
function focusInPanel(element: HTMLElement | undefined): void {
  if (!element || !panel.value) return
  element.focus({ preventScroll: true })
  if (element === panel.value) return
  const bounds = panel.value.getBoundingClientRect()
  const item = element.getBoundingClientRect()
  if (item.top < bounds.top) panel.value.scrollTop += item.top - bounds.top
  else if (item.bottom > bounds.bottom)
    panel.value.scrollTop += item.bottom - bounds.bottom
}
async function openWithFocus(last = false): Promise<void> {
  cancelClose()
  emit('update:open', true)
  await nextTick()
  position()
  await nextTick()
  const items = focusables()
  focusInPanel((last ? items.at(-1) : items[0]) ?? panel.value ?? undefined)
}
function activate(event: MouseEvent): void {
  if (event.detail === 0) {
    if (props.open) void close(true)
    else void openWithFocus()
  } else if (hoverAvailable()) {
    cancelClose()
    emit('update:open', true)
  } else {
    cancelClose()
    emit('update:open', !props.open)
  }
}
function triggerKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    void openWithFocus(event.key === 'ArrowUp')
  }
}
function keydown(event: KeyboardEvent): void {
  if (!props.open) return
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    void close(true)
  } else if (
    panel.value?.contains(event.target as Node) &&
    ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)
  ) {
    const items = focusables()
    if (!items.length) return
    event.preventDefault()
    const index = items.indexOf(document.activeElement as HTMLElement)
    const next =
      event.key === 'Home'
        ? 0
        : event.key === 'End'
          ? items.length - 1
          : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) %
            items.length
    focusInPanel(items[next])
  }
}
function outside(event: Event): void {
  if (!props.open || root.value?.contains(event.target as Node)) return
  const focusable = (event.target as Element).closest(
    'a[href], button, input, select, textarea, [tabindex], [contenteditable="true"]',
  )
  void close(event.type === 'click' && !focusable)
}
function outsideKeydown(event: KeyboardEvent): void {
  if (props.open && event.key === 'Escape' && !event.defaultPrevented) {
    event.preventDefault()
    void close()
  }
}
function position(): void {
  if (!props.open || !root.value || !panel.value) return
  const anchor = root.value.getBoundingClientRect()
  const rect = panel.value.getBoundingClientRect()
  // Read resolved lengths: the spacing tokens themselves contain calc/rem.
  const style = getComputedStyle(panel.value)
  const gutter = parseFloat(style.scrollPaddingTop)
  const desired =
    props.align === 'end' ? anchor.right - rect.width : anchor.left
  offset.value =
    Math.max(gutter, Math.min(desired, innerWidth - gutter - rect.width)) -
    anchor.left
  const gap = parseFloat(style.scrollMarginLeft)
  const naturalHeight =
    panel.value.scrollHeight +
    parseFloat(style.borderTopWidth) +
    parseFloat(style.borderBottomWidth)
  const below = innerHeight - anchor.bottom - gap - gutter
  const above = anchor.top - gap - gutter
  const flip = below < naturalHeight && above > below
  const available = Math.max(
    0,
    Math.min(innerHeight - gutter * 2, flip ? above : below),
  )
  const height = Math.min(naturalHeight, available)
  const desiredTop = flip ? anchor.top - gap - height : anchor.bottom + gap
  offsetTop.value =
    Math.max(gutter, Math.min(desiredTop, innerHeight - gutter - height)) -
    anchor.top
  maxHeight.value = available
}
watch(
  () => props.open,
  async (open) => {
    cancelClose()
    if (open) {
      await nextTick()
      position()
    }
  },
)
onMounted(() => {
  document.addEventListener('click', outside)
  document.addEventListener('focusin', outside)
  document.addEventListener('keydown', outsideKeydown)
  window.addEventListener('resize', position)
  window.addEventListener('scroll', position, true)
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(position)
    if (root.value) resizeObserver.observe(root.value)
  }
  position()
})
onBeforeUnmount(() => {
  cancelClose()
  document.removeEventListener('click', outside)
  document.removeEventListener('focusin', outside)
  document.removeEventListener('keydown', outsideKeydown)
  window.removeEventListener('resize', position)
  window.removeEventListener('scroll', position, true)
  resizeObserver?.disconnect()
})
const trigger = computed(() => ({
  'aria-expanded': props.open,
  'aria-controls': panelId.value,
  onClick: activate,
  onKeydown: triggerKeydown,
}))
</script>

<template>
  <div
    ref="root"
    class="ui-popover"
    @pointerenter="pointerEnter"
    @pointerleave="pointerLeave"
    @keydown="keydown"
  >
    <slot name="trigger" :trigger="trigger" :open="open" />
    <section
      v-if="open"
      :id="panelId"
      ref="panel"
      class="ui-popover-panel admin-focus"
      :class="panelClass"
      :style="{
        left: `${offset}px`,
        top: offsetTop === undefined ? undefined : `${offsetTop}px`,
        maxHeight: maxHeight === undefined ? undefined : `${maxHeight}px`,
      }"
      role="region"
      :aria-label="label"
      tabindex="-1"
    >
      <slot :close="() => close(true)" />
    </section>
  </div>
</template>

<style scoped>
.ui-popover {
  position: relative;
  min-width: 0;
  width: fit-content;
}
.ui-popover-panel {
  position: absolute;
  z-index: 2;
  top: calc(100% + var(--admin-spacing-2));
  width: var(--ui-popover-width, var(--admin-popover-width));
  max-width: calc(100vw - var(--admin-spacing-4) * 2);
  max-height: calc(100dvh - var(--admin-spacing-4) * 2);
  overflow-y: auto;
  overscroll-behavior: contain;
  scroll-padding-block: var(--admin-spacing-4);
  scroll-margin-inline: var(--admin-spacing-2);
  padding: var(--admin-spacing-6);
  border: var(--admin-border-width) solid var(--admin-color-gray-100);
  border-radius: var(--admin-radius-xl);
  background: var(--admin-color-white);
  box-shadow: var(--admin-shadow-dropdown);
}
</style>
