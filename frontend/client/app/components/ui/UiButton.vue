<script setup lang="ts">
withDefaults(
  defineProps<{
    to?: string
    variant?: 'dark' | 'light' | 'outline'
    size?: 'normal' | 'compact'
    type?: 'button' | 'submit'
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
  </NuxtLink>
  <button
    v-else
    :type="type"
    class="ui-button"
    :class="[`ui-button--${variant}`, `ui-button--${size}`]"
    @click="$emit('click', $event)"
  >
    <slot />
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
</style>
