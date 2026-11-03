<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'

withDefaults(
  defineProps<{
    to?: string
    variant?: 'dark' | 'light' | 'outline' | 'image-overlay'
    size?: 'normal' | 'compact'
    type?: 'button' | 'submit'
    arrow?: boolean
  }>(),
  {
    variant: 'dark',
    size: 'normal',
    type: 'button',
  },
)

defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <NuxtLink
    v-if="to"
    :to="to"
    class="ui-button"
    :class="[`ui-button--${variant}`, `ui-button--${size}`]"
  >
    <slot />
    <ArrowRight
      v-if="arrow"
      class="ui-button__arrow"
      :size="16"
      aria-hidden="true"
    />
  </NuxtLink>
  <button
    v-else
    :type="type"
    class="ui-button"
    :class="[`ui-button--${variant}`, `ui-button--${size}`]"
    @click="$emit('click', $event)"
  >
    <slot />
    <ArrowRight
      v-if="arrow"
      class="ui-button__arrow"
      :size="16"
      aria-hidden="true"
    />
  </button>
</template>

<style scoped>
.ui-button {
  display: inline-flex;
  min-height: 52px;
  align-items: center;
  justify-content: center;
  gap: 12px;
  border: 1px solid transparent;
  padding: 15px 32px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.18em;
  line-height: 1.5;
  text-align: center;
  text-transform: uppercase;
  transition:
    background 0.3s,
    color 0.3s,
    transform 0.3s;
}

.ui-button:hover {
  transform: translateY(-2px);
}
.ui-button__arrow {
  flex: none;
  transition: transform 0.3s;
}
.ui-button:not(:disabled):is(:hover, :focus-visible) .ui-button__arrow {
  transform: translateX(4px);
}
@media (prefers-reduced-motion: reduce) {
  .ui-button:not(:disabled):is(:hover, :focus-visible) .ui-button__arrow {
    transform: none;
  }
}

.ui-button--dark {
  background: var(--color-ink);
  color: var(--color-white);
}

.ui-button--dark:hover {
  background: var(--color-ink-hover);
}

.ui-button--light {
  background: var(--color-white);
  color: var(--color-ink);
}

.ui-button--light:hover {
  background: var(--color-bg);
}

.ui-button:disabled {
  opacity: 0.55;
  transform: none;
}

.ui-button--outline {
  border-color: var(--color-ink);
  background: transparent;
  color: var(--color-ink);
}

.ui-button--outline:hover {
  transform: none;
}

.ui-button--outline:not(:disabled):hover {
  background: var(--color-ink);
  color: var(--color-white);
}

.ui-button--compact {
  min-height: 44px;
  padding: 14px 22px;
  font-size: 10px;
  letter-spacing: 0.2em;
  white-space: nowrap;
}

.ui-button--image-overlay {
  position: absolute;
  inset: auto 0 0;
  width: 100%;
  min-height: 48px;
  border: 0;
  padding: 16px;
  background: var(--color-image-action-surface);
  color: var(--color-white);
  font-size: 10px;
  letter-spacing: 0.22em;
  backdrop-filter: blur(4px);
  transform: translateY(var(--image-action-offset, 101%));
  transition:
    transform 0.45s var(--ease-out),
    background 0.3s;
}

.ui-button--image-overlay:hover {
  transform: translateY(var(--image-action-offset, 101%));
}

.ui-button--image-overlay:not(:disabled):hover {
  background: var(--color-image-action-hover);
}

.ui-button--image-overlay:focus-visible {
  transform: none;
  outline-offset: -4px;
  outline-color: var(--color-white);
}

@media (hover: none) {
  .ui-button--image-overlay {
    position: static;
    margin-top: 14px;
    background: var(--color-ink);
    transform: none;
  }

  .ui-button--image-overlay:hover {
    transform: none;
  }
}
</style>
