<script setup lang="ts">
import { computed, useAttrs } from 'vue'
defineOptions({ inheritAttrs: false })
const props = withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'danger-ghost'; size?: 'sm' | 'md' | 'lg'; loading?: boolean; disabled?: boolean }>(), { variant: 'primary', size: 'md', loading: false, disabled: false })
const attrs = useAttrs()
const classes = computed(() => [
  'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2',
  props.size === 'sm' ? 'min-h-[var(--admin-control-height-sm)] px-3 py-1.5 text-xs' : props.size === 'lg' ? 'min-h-[var(--admin-control-height-lg)] px-5 py-3 text-base' : 'min-h-[var(--admin-control-height-md)] px-4 py-2.5 text-sm',
  props.variant === 'primary' ? 'bg-primary-500 text-white hover:bg-primary-600 disabled:bg-gray-500 disabled:hover:bg-gray-500' : props.variant === 'danger' ? 'bg-error-500 text-white hover:bg-error-700 disabled:bg-gray-500 disabled:hover:bg-gray-500' : props.variant === 'danger-ghost' ? 'text-error-500 hover:bg-error-50 hover:text-error-700 disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500' : props.variant === 'secondary' ? 'border border-gray-300 text-gray-600 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600 disabled:border-gray-200 disabled:bg-gray-50 disabled:text-gray-500 disabled:hover:border-gray-200 disabled:hover:bg-gray-50 disabled:hover:text-gray-500' : 'text-gray-600 hover:bg-gray-50 disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500',
  attrs.class,
])
const forwarded = computed(() => { const { class: ignored, ...rest } = attrs; return rest })
</script>
<template><button v-bind="forwarded" :class="classes" :disabled="disabled || loading"><span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"/><slot/></button></template>
