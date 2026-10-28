<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const anchor = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const floating = ref(false)
const position = ref({ top: '0px', right: '0px', maxWidth: '0px' })
const placeholder = ref<{ width?: string; height?: string }>({})
let observer: ResizeObserver | undefined
let frame = 0

function update(): void {
  if (!anchor.value || !panel.value) return
  const header = document.querySelector('.admin-header')
  const bottom = header?.getBoundingClientRect().bottom ?? 0
  const gap = parseFloat(getComputedStyle(panel.value).columnGap) || 0
  const bounds = anchor.value.getBoundingClientRect()
  const next = bounds.top < bottom + gap
  if (!floating.value) {
    // Keep the original space while the same controls move to the body portal.
    placeholder.value = {
      width: `${bounds.width}px`,
      height: `${bounds.height}px`,
    }
  } else {
    placeholder.value.height = `${panel.value.getBoundingClientRect().height}px`
  }
  const availableWidth = window.innerWidth - gap * 2
  const panelWidth = Math.min(
    panel.value.getBoundingClientRect().width,
    availableWidth,
  )
  const right = Math.max(
    gap,
    Math.min(
      window.innerWidth - bounds.right,
      window.innerWidth - panelWidth - gap,
    ),
  )
  position.value = {
    top: `${bottom + gap}px`,
    right: `${right}px`,
    maxWidth: `${Math.max(0, availableWidth)}px`,
  }
  if (floating.value !== next) {
    const focused = document.activeElement
    floating.value = next
    if (focused instanceof HTMLElement && panel.value.contains(focused)) {
      void nextTick(() => focused.focus({ preventScroll: true }))
    }
  }
}

function scheduleUpdate(): void {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(update)
}

onMounted(() => {
  observer = new ResizeObserver(scheduleUpdate)
  if (anchor.value) observer.observe(anchor.value)
  if (panel.value) observer.observe(panel.value)
  const header = document.querySelector('.admin-header')
  if (header) observer.observe(header)
  window.addEventListener('scroll', scheduleUpdate, { passive: true })
  window.addEventListener('resize', scheduleUpdate)
  update()
})

onBeforeUnmount(() => {
  observer?.disconnect()
  cancelAnimationFrame(frame)
  window.removeEventListener('scroll', scheduleUpdate)
  window.removeEventListener('resize', scheduleUpdate)
})
</script>

<template>
  <div
    ref="anchor"
    class="admin-sticky-actions-anchor"
    :style="floating ? placeholder : undefined"
  >
    <Teleport to="body" :disabled="!floating">
      <div
        ref="panel"
        class="admin-sticky-actions"
        :class="{ 'admin-sticky-actions--floating': floating }"
        :style="floating ? position : undefined"
      >
        <slot />
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.admin-sticky-actions-anchor {
  min-width: 0;
  max-width: 100%;
}
.admin-sticky-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--admin-spacing-2);
}
.admin-sticky-actions--floating {
  position: fixed;
  width: max-content;
  z-index: 25;
  isolation: isolate;
}
.admin-sticky-actions--floating::before {
  position: absolute;
  z-index: -1;
  inset: calc(-1 * var(--admin-spacing-2));
  border-radius: var(--admin-radius-lg);
  background: var(--admin-color-white);
  box-shadow: var(--admin-shadow-dropdown);
  content: '';
}
</style>
