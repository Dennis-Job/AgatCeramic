<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  labelledby: string
  describedby?: string
  closeDisabled?: boolean
  suspended?: boolean
  overlayClass?: string
  panelClass?: string
}>(), {
  describedby: undefined,
  closeDisabled: false,
  suspended: false,
  overlayClass: 'z-50 grid place-items-center p-4',
  panelClass: '',
})

const emit = defineEmits<{ close: [] }>()
const panel = ref<HTMLElement | null>(null)
let opener: HTMLElement | null = null

const focusableSelector = [
  'button:not([disabled])',
  '[href]',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

function focusableElements(): HTMLElement[] {
  return panel.value ? Array.from(panel.value.querySelectorAll<HTMLElement>(focusableSelector)) : []
}

function requestClose(): void {
  if (!props.closeDisabled && !props.suspended) emit('close')
}

function handleKeydown(event: KeyboardEvent): void {
  if (props.suspended) return
  if (event.key === 'Escape') {
    event.preventDefault()
    requestClose()
    return
  }
  if (event.key !== 'Tab') return
  const elements = focusableElements()
  if (!elements.length) {
    event.preventDefault()
    panel.value?.focus()
    return
  }
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

watch(() => props.open, async (open) => {
  if (open) {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
    await nextTick()
    const initial = panel.value?.querySelector<HTMLElement>('[data-autofocus]') ?? focusableElements()[0] ?? panel.value
    initial?.focus()
  } else if (opener) {
    await nextTick()
    if (opener.isConnected) opener.focus()
    opener = null
  }
}, { flush: 'post' })

onBeforeUnmount(() => {
  if (opener?.isConnected) opener.focus()
})
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 bg-gray-900/50"
    :class="overlayClass"
    :aria-hidden="suspended ? 'true' : undefined"
    :inert="suspended ? true : undefined"
    @click.self="requestClose"
    @keydown="handleKeydown"
  >
    <section
      ref="panel"
      class="admin-dialog-content"
      :class="panelClass"
      :role="suspended ? undefined : 'dialog'"
      :aria-modal="suspended ? undefined : 'true'"
      :aria-labelledby="suspended ? undefined : labelledby"
      :aria-describedby="suspended ? undefined : describedby"
      tabindex="-1"
    >
      <slot />
    </section>
  </div>
</template>
