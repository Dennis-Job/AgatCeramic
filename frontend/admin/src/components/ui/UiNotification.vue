<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  onUpdated,
  ref,
} from 'vue'
import { X } from '@lucide/vue'
import UiAlert from './UiAlert.vue'
import UiButton from './UiButton.vue'
import { notificationOpener } from '../../composables/useNotificationContext'

defineOptions({ inheritAttrs: false })
const emit = defineEmits<{ dismiss: [] }>()
const NOTIFICATION_DURATION = 5000
const props = withDefaults(
  defineProps<{
    tone?: 'error' | 'success' | 'warning' | 'info'
    live?: 'assertive' | 'polite'
  }>(),
  { tone: 'error', live: undefined },
)
const notificationLive = computed(
  () => props.live ?? (props.tone === 'error' ? 'assertive' : 'polite'),
)
const visible = ref(true)
const ready = ref(false)
const content = ref<HTMLElement | null>(null)
let opener: HTMLElement | null = null
let openerTrigger: HTMLElement | null = null
let message = ''
let timer: ReturnType<typeof setTimeout> | undefined
let remaining = 0
let started = 0
let hovered = false
let focused = false

function stopTimer(): void {
  if (timer === undefined) return
  clearTimeout(timer)
  timer = undefined
  remaining = Math.max(0, remaining - (Date.now() - started))
}
function startTimer(): void {
  if (!visible.value || hovered || focused || document.hidden || remaining <= 0)
    return
  stopTimer()
  started = Date.now()
  timer = setTimeout(() => {
    timer = undefined
    visible.value = false
    emit('dismiss')
  }, remaining)
}
function resetTimer(): void {
  stopTimer()
  remaining = NOTIFICATION_DURATION
  startTimer()
}
function pauseHover(): void {
  hovered = true
  stopTimer()
}
function resumeHover(): void {
  hovered = false
  startTimer()
}
function pauseFocus(): void {
  focused = true
  stopTimer()
}
function resumeFocus(event: FocusEvent): void {
  if (
    event.relatedTarget instanceof Node &&
    content.value?.parentElement?.contains(event.relatedTarget)
  )
    return
  focused = false
  startTimer()
}
function visibilityChanged(): void {
  if (document.hidden) stopTimer()
  else startTimer()
}
async function dismiss(): Promise<void> {
  const card = content.value?.parentElement
  const hadFocus = card?.contains(document.activeElement)
  stopTimer()
  visible.value = false
  emit('dismiss')
  if (!hadFocus) return
  await nextTick()
  const nextControl = Array.from(
    document.querySelectorAll<HTMLElement>(
      '[data-notification] button:not(:disabled), [data-notification] a[href]',
    ),
  ).find((element) => element.getClientRects().length > 0)
  const modal = Array.from(
    document.querySelectorAll<HTMLElement>(
      '[role="dialog"][aria-modal="true"]',
    ),
  ).at(-1)
  const returnSource = [opener, openerTrigger].find(
    (control) =>
      control?.isConnected &&
      !control.matches(':disabled') &&
      (!modal || modal.contains(control)),
  )
  const returnTarget = returnSource
    ? returnSource
    : (Array.from(
        (
          modal ??
          document.querySelector('main') ??
          document.body
        ).querySelectorAll<HTMLElement>(
          'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [href]',
        ),
      ).find(
        (control) =>
          !control.closest('[data-notification]') &&
          control.getClientRects().length > 0,
      ) ?? modal)
  const target = nextControl ?? returnTarget
  target?.focus({ preventScroll: true })
}
onMounted(async () => {
  opener =
    document.activeElement instanceof HTMLElement &&
    document.activeElement !== document.body &&
    !document.activeElement.closest('[data-notification]')
      ? document.activeElement
      : notificationOpener.value
  const floatingPanel = opener?.closest<HTMLElement>('[data-floating-popover]')
  if (floatingPanel?.id)
    openerTrigger =
      Array.from(
        document.querySelectorAll<HTMLElement>('[aria-controls]'),
      ).find(
        (control) => control.getAttribute('aria-controls') === floatingPanel.id,
      ) ?? null
  ready.value = true
  await nextTick()
  message = content.value?.textContent ?? ''
  resetTimer()
  document.addEventListener('visibilitychange', visibilityChanged)
})
onUpdated(() => {
  const current = content.value?.textContent ?? ''
  if (current === message) return
  message = current
  visible.value = true
  resetTimer()
})
onBeforeUnmount(() => {
  stopTimer()
  document.removeEventListener('visibilitychange', visibilityChanged)
})
</script>

<template>
  <Teleport v-if="ready" to="#admin-notifications">
    <UiAlert
      v-show="visible"
      class="admin-notification"
      :tone="props.tone"
      :live="notificationLive"
      data-notification
      @mouseenter="pauseHover"
      @mouseleave="resumeHover"
      @focusin="pauseFocus"
      @focusout="resumeFocus"
      @keydown.esc.stop.prevent="dismiss"
    >
      <span ref="content" class="admin-notification-content"><slot /></span>
      <UiButton
        variant="surface"
        size="sm"
        class="admin-notification-close"
        aria-label="Закрыть уведомление"
        @click="dismiss"
        ><X :size="16" aria-hidden="true"
      /></UiButton>
    </UiAlert>
  </Teleport>
</template>
