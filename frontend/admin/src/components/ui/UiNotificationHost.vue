<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue'
import {
  notificationDialog,
  notificationOpener,
} from '../../composables/useNotificationContext'

function rememberControl(event: Event): void {
  if (
    !(event.target instanceof Element) ||
    event.target.closest('[data-notification]')
  )
    return
  const control = event.target.closest<HTMLElement>(
    'button, a[href], input, select, textarea, [tabindex]',
  )
  if (control) notificationOpener.value = control
}
onMounted(() => {
  document.addEventListener('pointerdown', rememberControl, true)
  document.addEventListener('focusin', rememberControl, true)
})
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', rememberControl, true)
  document.removeEventListener('focusin', rememberControl, true)
  notificationOpener.value = null
})
</script>
<template>
  <Teleport :to="notificationDialog ?? 'body'">
    <aside
      id="admin-notifications"
      class="admin-notifications"
      aria-label="Уведомления"
      data-floating-notifications
    />
  </Teleport>
</template>
