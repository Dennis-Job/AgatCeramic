<script setup lang="ts">
import { X } from '@lucide/vue'

defineProps<{ title: string; busy?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null
let previousOverflow = ''

onMounted(() => {
  previousFocus = document.activeElement as HTMLElement | null
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  dialog.value?.showModal()
  dialog.value?.focus({ preventScroll: true })
})
onBeforeUnmount(() => {
  dialog.value?.close()
  document.body.style.overflow = previousOverflow
  if (previousFocus?.isConnected) previousFocus.focus()
})

function isBackdrop(event: MouseEvent) {
  if (!dialog.value || event.target !== dialog.value) return false
  const rect = dialog.value.getBoundingClientRect()
  return (
    event.clientX < rect.left ||
    event.clientX > rect.right ||
    event.clientY < rect.top ||
    event.clientY > rect.bottom
  )
}

function trapFocus(event: KeyboardEvent) {
  const controls = Array.from(
    dialog.value?.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), [tabindex="0"]',
    ) ?? [],
  )
  const first = controls[0]
  const last = controls[controls.length - 1]
  if (
    event.shiftKey &&
    (document.activeElement === first ||
      document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}
</script>

<template>
  <dialog
    ref="dialog"
    class="ui-dialog"
    :aria-labelledby="titleId"
    @keydown.tab="trapFocus"
    @cancel.prevent="!busy && emit('close')"
    @click="!busy && isBackdrop($event) && emit('close')"
  >
    <div class="ui-dialog__heading">
      <h2 :id="titleId">{{ title }}</h2>
      <button
        type="button"
        class="ui-dialog__close"
        aria-label="Закрыть"
        :disabled="busy"
        @click="emit('close')"
      >
        <X :size="20" aria-hidden="true" />
      </button>
    </div>
    <slot />
  </dialog>
</template>

<style scoped>
.ui-dialog {
  width: min(520px, calc(100% - 40px));
  max-height: calc(100dvh - 40px);
  overflow-y: auto;
  border: 1px solid var(--color-line);
  padding: 36px;
  background: var(--color-bg-soft);
  color: var(--color-ink);
  overflow-wrap: anywhere;
  outline: none;
}
.ui-dialog::backdrop {
  background: var(--color-overlay);
  backdrop-filter: blur(3px);
}
.ui-dialog__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}
h2 {
  margin: 0;
  font-size: clamp(24px, 4vw, 30px);
  font-weight: 500;
  letter-spacing: -0.01em;
}
.ui-dialog__close {
  display: grid;
  place-items: center;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  border: 1px solid var(--color-line);
  border-radius: 50%;
  background: transparent;
  color: inherit;
}
.ui-dialog__close:hover:not(:disabled) {
  background: var(--color-beige);
}
.ui-dialog__close:disabled {
  opacity: 0.55;
}
@media (max-width: 640px) {
  .ui-dialog {
    padding: 24px 20px;
  }
}
</style>
