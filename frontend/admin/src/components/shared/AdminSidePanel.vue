<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { X } from '@lucide/vue'
import UiDialog from '../ui/UiDialog.vue'
import UiButton from '../ui/UiButton.vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    titleId: string
    closeLabel: string
    busy?: boolean
    suspended?: boolean
    overlayClass?: string
    panelClass?: string
    transitionName?: string
  }>(),
  {
    busy: false,
    suspended: false,
    overlayClass: '',
    panelClass: '',
    transitionName: 'admin-side-panel',
  },
)
defineEmits<{ close: [] }>()
const heading = ref<HTMLElement | null>(null)
let overflow: string | null = null
let paddingRight: string | null = null
function unlock() {
  if (overflow === null) return
  document.body.style.overflow = overflow
  document.body.style.paddingRight = paddingRight ?? ''
  overflow = null
  paddingRight = null
}
watch(
  () => props.open,
  (open) => {
    if (!open) return unlock()
    if (overflow !== null) return
    overflow = document.body.style.overflow
    paddingRight = document.body.style.paddingRight
    const width = window.innerWidth - document.documentElement.clientWidth
    if (width > 0)
      document.body.style.paddingRight = `${parseFloat(getComputedStyle(document.body).paddingRight) + width}px`
    document.body.style.overflow = 'hidden'
  },
  { immediate: true },
)
onBeforeUnmount(unlock)
onMounted(async () => {
  if (!props.open || props.suspended) return
  await nextTick()
  heading.value?.focus({ preventScroll: true })
})
</script>

<template>
  <UiDialog
    :open="open"
    :labelledby="titleId"
    :close-disabled="busy"
    :suspended="suspended"
    :overlay-class="`admin-side-panel-overlay z-40 flex items-stretch justify-end ${overlayClass}`"
    :panel-class="`admin-side-panel ${panelClass}`"
    :transition-name="transitionName"
    @close="$emit('close')"
  >
    <div class="admin-side-panel-heading">
      <h2
        :id="titleId"
        ref="heading"
        class="text-xl font-bold text-gray-500"
        tabindex="-1"
        data-autofocus
      >
        {{ title }}
      </h2>
      <UiButton
        variant="neutral-ghost"
        size="sm"
        :aria-label="closeLabel"
        :disabled="busy"
        @click="$emit('close')"
        ><X :size="20" aria-hidden="true"
      /></UiButton>
    </div>
    <div class="admin-side-panel-body"><slot /></div>
  </UiDialog>
</template>

<style scoped>
:global(.admin-side-panel-overlay) {
  --admin-dialog-backdrop-filter: none;
  padding-top: var(--admin-shell-height);
  background: transparent;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}
:global(.admin-side-panel-overlay .admin-side-panel) {
  width: min(544px, 100%);
  height: 100%;
  max-height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;
  border-left: var(--admin-border-width) solid var(--admin-color-gray-200);
  border-radius: 0;
  box-shadow: var(--admin-shadow-dropdown);
}
.admin-side-panel-heading {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--admin-spacing-3);
  padding: var(--admin-spacing-6);
  background: var(--admin-color-white);
}
.admin-side-panel-heading h2 {
  min-width: 0;
  overflow-wrap: anywhere;
  outline: none;
}
.admin-side-panel-body {
  padding: 0 var(--admin-spacing-6) var(--admin-spacing-6);
}
:global(.admin-side-panel-enter-active),
:global(.admin-side-panel-leave-active),
:global(.contacts-panel-enter-active),
:global(.contacts-panel-leave-active) {
  transition: opacity var(--admin-transition-duration)
    var(--admin-transition-timing);
}
:global(.admin-side-panel-enter-from),
:global(.admin-side-panel-leave-to),
:global(.contacts-panel-enter-from),
:global(.contacts-panel-leave-to) {
  opacity: 0;
}
:global(.admin-side-panel-enter-active .admin-side-panel),
:global(.admin-side-panel-leave-active .admin-side-panel),
:global(.contacts-panel-enter-active .admin-side-panel),
:global(.contacts-panel-leave-active .admin-side-panel) {
  transition: transform var(--admin-transition-duration)
    var(--admin-transition-timing);
}
:global(.admin-side-panel-enter-from .admin-side-panel),
:global(.admin-side-panel-leave-to .admin-side-panel),
:global(.contacts-panel-enter-from .admin-side-panel),
:global(.contacts-panel-leave-to .admin-side-panel) {
  transform: translateX(100%);
}
:global(.admin-side-panel-leave-active),
:global(.contacts-panel-leave-active) {
  pointer-events: none;
}
@media (prefers-reduced-motion: reduce) {
  :global(.admin-side-panel-enter-active),
  :global(.admin-side-panel-leave-active),
  :global(.contacts-panel-enter-active),
  :global(.contacts-panel-leave-active),
  :global(.admin-side-panel-enter-active .admin-side-panel),
  :global(.admin-side-panel-leave-active .admin-side-panel),
  :global(.contacts-panel-enter-active .admin-side-panel),
  :global(.contacts-panel-leave-active .admin-side-panel) {
    transition: none;
  }
}
</style>
