<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { vTooltip } from './tooltip'
defineOptions({ inheritAttrs: false })
const props = withDefaults(
  defineProps<{
    variant?:
      | 'primary'
      | 'secondary'
      | 'soft-blue'
      | 'surface'
      | 'danger'
      | 'neutral-ghost'
      | 'primary-ghost'
      | 'danger-ghost'
    size?: 'sm' | 'md' | 'lg'
    loading?: boolean
    disabled?: boolean
    tooltip?: string | false
  }>(),
  {
    variant: 'primary',
    size: 'md',
    loading: false,
    disabled: false,
    tooltip: false,
  },
)
const attrs = useAttrs()
const classes = computed(() => [
  'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition disabled:cursor-not-allowed admin-focus',
  props.size === 'sm'
    ? 'min-h-[var(--admin-control-height-sm)] px-3 py-1.5 text-xs'
    : props.size === 'lg'
      ? 'min-h-[var(--admin-control-height-lg)] px-5 py-2 text-base'
      : 'min-h-[var(--admin-control-height-md)] px-4 py-1.5 text-sm',
  props.variant === 'primary'
    ? 'bg-primary-500 text-white hover:bg-primary-600 disabled:bg-gray-400 disabled:hover:bg-gray-400'
    : props.variant === 'danger'
      ? 'bg-error-500 text-white hover:bg-error-600 disabled:bg-gray-400 disabled:hover:bg-gray-400'
      : props.variant === 'danger-ghost'
        ? 'text-error-500 hover:bg-error-50 focus-visible:bg-error-50 hover:text-error-500 disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500'
        : props.variant === 'neutral-ghost'
          ? 'text-gray-500 hover:bg-gray-100 focus-visible:bg-gray-100 disabled:bg-transparent disabled:text-gray-400 disabled:hover:bg-transparent'
          : props.variant === 'primary-ghost'
            ? 'text-primary-500 hover:bg-primary-50 focus-visible:bg-primary-50 disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500'
            : props.variant === 'surface'
              ? 'bg-white text-gray-500 hover:bg-primary-50 hover:text-primary-500 disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500'
              : props.variant === 'soft-blue'
                ? 'bg-blue-light-100 text-blue-light-500 hover:bg-blue-light-200 focus-visible:bg-blue-light-200 disabled:bg-gray-100 disabled:text-gray-500 disabled:hover:bg-gray-100'
                : 'border border-transparent bg-gray-50 text-gray-500 hover:bg-gray-100 disabled:bg-gray-100 disabled:text-gray-500 disabled:hover:bg-gray-100 disabled:hover:text-gray-500',
  attrs.class,
])
const forwarded = computed(() => {
  const rest = { ...attrs }
  delete rest.class
  return rest
})
</script>
<template>
  <button
    v-tooltip="tooltip"
    v-bind="forwarded"
    :class="classes"
    :disabled="disabled || loading"
  >
    <span
      v-if="loading"
      class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    /><slot />
  </button>
</template>
