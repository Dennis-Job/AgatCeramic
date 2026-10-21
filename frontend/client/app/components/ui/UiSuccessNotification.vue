<script setup lang="ts">
const { notification, dismiss } = useSuccessNotification()
let timer: ReturnType<typeof setTimeout> | undefined

watch(notification, (value) => {
  clearTimeout(timer)
  if (value) timer = setTimeout(dismiss, 2600)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  dismiss()
})
</script>

<template>
  <div class="success-notification" role="status" aria-atomic="true">
    <Transition name="success-notification">
      <p v-if="notification" class="success-notification__message">
        {{ notification.message }}
      </p>
    </Transition>
  </div>
</template>

<style scoped>
.success-notification {
  position: fixed;
  left: 50%;
  bottom: calc(32px + env(safe-area-inset-bottom, 0px));
  transform: translateX(-50%);
  width: max-content;
  max-width: calc(100% - 40px);
  z-index: 200;
  pointer-events: none;
}
.success-notification__message {
  margin: 0;
  padding: 15px 28px;
  background: var(--color-ink);
  color: var(--color-white);
  font-size: 12px;
  letter-spacing: 0.1em;
  overflow-wrap: anywhere;
}
.success-notification-enter-active,
.success-notification-leave-active {
  transition: transform var(--motion-backdrop-duration) var(--ease-out);
}
.success-notification-enter-from,
.success-notification-leave-to {
  transform: translateY(calc(100% + 32px + env(safe-area-inset-bottom, 0px)));
}
@media (prefers-reduced-motion: reduce) {
  .success-notification-enter-from,
  .success-notification-leave-to {
    transform: none;
  }
  .success-notification-enter-active,
  .success-notification-leave-active {
    transition: none;
  }
}
</style>
