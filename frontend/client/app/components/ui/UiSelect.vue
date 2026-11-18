<script setup lang="ts">
import { Check } from '@lucide/vue'

const props = defineProps<{
  label: string
  options: { value: string; label: string }[]
  disabled?: boolean
}>()
const model = defineModel<string>({ required: true })
const id = useId()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const open = ref(false)
const active = ref(0)
const position = ref<Record<string, string>>({})
const route = useRoute()
const headerHeight = useSiteHeaderHeight()
const selected = computed(() =>
  props.options.findIndex((option) => option.value === model.value),
)
const text = computed(() => props.options[selected.value]?.label ?? '')
let observer: ResizeObserver | undefined
let search = ''
let lastTyped = 0

function close() {
  open.value = false
  search = ''
  if (panel.value?.matches(':popover-open')) panel.value.hidePopover()
}

function updatePosition() {
  if (!open.value || !trigger.value) return
  const bounds = trigger.value.getBoundingClientRect()
  if (
    !trigger.value.getClientRects().length ||
    bounds.top < headerHeight.value - 1 ||
    bounds.top > innerHeight
  )
    return close()
  const margin = 8
  const width = Math.min(
    bounds.width,
    document.documentElement.clientWidth - margin * 2,
  )
  const below = innerHeight - bounds.bottom - margin * 2
  const above = bounds.top - headerHeight.value - margin * 2
  const upwards = below < Math.min(220, above)
  position.value = {
    left: `${Math.max(margin, Math.min(bounds.left, document.documentElement.clientWidth - width - margin))}px`,
    width: `${width}px`,
    maxHeight: `${Math.max(0, Math.min(340, upwards ? above : below))}px`,
    top: upwards ? 'auto' : `${bounds.bottom + margin}px`,
    bottom: upwards ? `${innerHeight - bounds.top + margin}px` : 'auto',
  }
}

function revealActive() {
  const list = panel.value
  const option = list?.children[active.value] as HTMLElement | undefined
  if (!list || !option) return
  if (option.offsetTop < list.scrollTop) list.scrollTop = option.offsetTop
  else if (
    option.offsetTop + option.offsetHeight >
    list.scrollTop + list.clientHeight
  )
    list.scrollTop = option.offsetTop + option.offsetHeight - list.clientHeight
}

async function show() {
  if (
    trigger.value?.matches(':disabled') ||
    !props.options.length ||
    open.value
  )
    return
  active.value = Math.max(0, selected.value)
  open.value = true
  await nextTick()
  if (!open.value) return
  updatePosition()
  if (open.value) {
    panel.value?.showPopover()
    revealActive()
  }
}

function choose(index = active.value) {
  const option = props.options[index]
  if (!option || trigger.value?.matches(':disabled')) return close()
  model.value = option.value
  close()
}

async function onKeydown(event: KeyboardEvent) {
  if (props.disabled) return
  const key = event.key
  if (key === 'Escape' && open.value) {
    event.preventDefault()
    event.stopPropagation()
    return close()
  }
  if (key === 'Tab') {
    if (open.value) choose()
    return
  }
  if (key === 'Enter' || key === ' ') {
    event.preventDefault()
    if (open.value) choose()
    else await show()
    return
  }
  if (event.altKey && key === 'ArrowUp' && open.value) {
    event.preventDefault()
    return choose()
  }
  const moves: Record<string, number> = {
    ArrowDown: 1,
    ArrowUp: -1,
    PageDown: 10,
    PageUp: -10,
  }
  if (key in moves || key === 'Home' || key === 'End') {
    event.preventDefault()
    const wasOpen = open.value
    await show()
    if (key === 'Home') active.value = 0
    else if (key === 'End') active.value = props.options.length - 1
    else if (wasOpen)
      active.value = Math.max(
        0,
        Math.min(props.options.length - 1, active.value + moves[key]!),
      )
    await nextTick()
    revealActive()
  } else if (
    key.length === 1 &&
    !event.ctrlKey &&
    !event.metaKey &&
    !event.altKey
  ) {
    event.preventDefault()
    const now = Date.now()
    const previous = now - lastTyped < 700 ? search : ''
    search = previous + key.toLocaleLowerCase('ru')
    lastTyped = now
    await show()
    const repeated = [...search].every((char) => char === search[0])
    const query = repeated ? search[0]! : search
    const start = previous && !repeated ? active.value : active.value + 1
    for (let offset = 0; offset < props.options.length; offset++) {
      const index = (start + offset) % props.options.length
      if (
        props.options[index]!.label.toLocaleLowerCase('ru').startsWith(query)
      ) {
        active.value = index
        break
      }
    }
    await nextTick()
    revealActive()
  }
}

function onScroll(event: Event) {
  if (event.target instanceof Node && panel.value?.contains(event.target))
    return
  updatePosition()
}

function outside(event: Event) {
  if (event.target instanceof Node && !root.value?.contains(event.target))
    close()
}

watch(() => route.fullPath, close)
watch(headerHeight, updatePosition)
watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) close()
  },
)
watch(
  () => props.options,
  () => {
    if (!props.options.length) close()
    active.value = Math.max(0, selected.value)
    if (open.value) nextTick(revealActive)
  },
)
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  document.addEventListener('focusin', outside)
  window.addEventListener('resize', updatePosition)
  window.addEventListener('scroll', onScroll, true)
  observer = new ResizeObserver(updatePosition)
  if (root.value) observer.observe(root.value)
})
onBeforeUnmount(() => {
  close()
  observer?.disconnect()
  document.removeEventListener('pointerdown', outside)
  document.removeEventListener('focusin', outside)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', onScroll, true)
})
</script>

<template>
  <div ref="root" class="ui-select">
    <label :id="`${id}-label`" :for="id" class="ui-field-label">{{
      label
    }}</label>
    <button
      :id="id"
      ref="trigger"
      type="button"
      role="combobox"
      class="ui-select__trigger"
      :disabled="disabled || !options.length"
      :aria-labelledby="`${id}-label`"
      aria-haspopup="listbox"
      :aria-controls="`${id}-list`"
      :aria-expanded="open"
      :aria-activedescendant="open ? `${id}-option-${active}` : undefined"
      @click="open ? close() : show()"
      @keydown="onKeydown"
    >
      {{ text }}
    </button>
    <div
      :id="`${id}-list`"
      ref="panel"
      role="listbox"
      popover="manual"
      class="ui-select__panel ui-submenu-popover"
      :aria-labelledby="`${id}-label`"
      :inert="!open"
      :aria-hidden="!open"
      :style="position"
    >
      <div
        v-for="(option, index) in options"
        :id="`${id}-option-${index}`"
        :key="option.value"
        role="option"
        class="ui-select__option"
        :class="{ 'is-active': index === active }"
        :aria-selected="option.value === model"
        @pointerdown.prevent
        @click="choose(index)"
      >
        <span>{{ option.label }}</span>
        <Check
          v-if="option.value === model"
          :size="14"
          :stroke-width="1.5"
          aria-hidden="true"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.ui-select {
  display: grid;
  gap: 8px;
  min-width: 0;
}
.ui-select__trigger {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  border: 1px solid var(--color-line);
  border-radius: 0;
  padding: 10px 12px;
  background: transparent;
  color: var(--color-ink);
  font: inherit;
  font-size: 13px;
  line-height: 1.5;
  text-align: left;
  overflow-wrap: anywhere;
  outline: none;
}
.ui-select__trigger:focus-visible {
  text-decoration: underline;
  text-underline-offset: 4px;
}
.ui-select__trigger:disabled {
  opacity: 0.55;
}
.ui-select__panel {
  position: fixed;
  inset: auto;
  box-sizing: border-box;
  margin: 0;
  border: 1px solid var(--color-line);
  padding: 4px;
  overflow-y: auto;
  overscroll-behavior: contain;
  background: var(--color-bg-soft);
  color: var(--color-ink);
  box-shadow: 0 12px 28px var(--color-shadow-soft);
}
.ui-select__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 44px;
  padding: 10px 8px;
  font-size: 13px;
  line-height: 1.5;
  overflow-wrap: anywhere;
  cursor: pointer;
}
.ui-select__option span {
  min-width: 0;
}
.ui-select__option svg {
  flex-shrink: 0;
}
.ui-select__option:hover,
.ui-select__option.is-active {
  background: var(--color-beige);
}
.ui-select__option[aria-selected='true'] {
  font-weight: 500;
}
</style>
