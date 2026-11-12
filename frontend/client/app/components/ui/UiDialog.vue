<script setup lang="ts">
import UiCloseButton from './UiCloseButton.vue'

const props = defineProps<{
  title: string
  busy?: boolean
  size?: 'default' | 'wide' | 'drawer'
}>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()
const visible = ref(false)
let openedDialog: HTMLDialogElement | null = null
let previousFocus: HTMLElement | null = null
let previousOverflow = ''

function showDialog(element: Element) {
  previousFocus = document.activeElement as HTMLElement | null
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  openedDialog = element as HTMLDialogElement
  openedDialog.showModal()
  openedDialog.focus({ preventScroll: true })
}
function cleanup() {
  if (!openedDialog) return
  openedDialog.close()
  openedDialog = null
  document.body.style.overflow = previousOverflow
  if (previousFocus?.isConnected) previousFocus.focus()
}
function requestClose() {
  if (!props.busy) visible.value = false
}
function deactivateDialog(element: Element) {
  ;(element as HTMLElement).inert = true
}
function finishClose() {
  cleanup()
  emit('close')
}
onMounted(() => {
  visible.value = true
})
onBeforeUnmount(cleanup)

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
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]',
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
  <Transition
    :name="size === 'drawer' ? 'ui-drawer' : 'ui-modal'"
    @enter="showDialog"
    @before-leave="deactivateDialog"
    @after-leave="finishClose"
  >
    <dialog
      v-if="visible"
      ref="dialog"
      class="ui-dialog"
      :class="{
        'ui-dialog--wide': size === 'wide',
        'ui-dialog--drawer': size === 'drawer',
      }"
      :aria-labelledby="titleId"
      @keydown.tab="trapFocus"
      @cancel.prevent="requestClose"
      @click="isBackdrop($event) && requestClose()"
    >
      <div class="ui-dialog__heading">
        <h2>
          <span :id="titleId">{{ title }}</span
          ><slot name="title-extra" />
        </h2>
        <UiCloseButton :disabled="busy" @click="requestClose" />
      </div>
      <slot :close="requestClose" />
    </dialog>
  </Transition>
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
.ui-dialog--wide {
  width: min(860px, calc(100% - 40px));
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
@media (max-width: 640px) {
  .ui-dialog {
    padding: 24px 20px;
  }
}
.ui-dialog--drawer {
  --ui-drawer-gutter: 32px;
  position: fixed;
  inset: 0 0 0 auto;
  width: min(460px, 100%);
  height: 100dvh;
  max-width: none;
  max-height: none;
  margin: 0;
  overflow: hidden;
  border: 0;
  padding: 0;
}
.ui-dialog--drawer[open] {
  display: flex;
  flex-direction: column;
}
.ui-dialog--drawer .ui-dialog__heading {
  flex-shrink: 0;
  margin: 0;
  border-bottom: 1px solid var(--color-line);
  padding: 28px var(--ui-drawer-gutter);
}
.ui-dialog--drawer h2 {
  font-size: 14px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}
@media (max-width: 460px) {
  .ui-dialog--drawer {
    --ui-drawer-gutter: 20px;
  }
}
</style>
