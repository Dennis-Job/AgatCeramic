<script setup lang="ts">
import { computed, useAttrs } from 'vue'
defineOptions({ inheritAttrs: false })
const props = withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'danger' | 'ghost'; size?: 'sm' | 'md' | 'lg'; loading?: boolean }>(), { variant: 'primary', size: 'md', loading: false })
const attrs = useAttrs()
const classes = computed(() => [
  'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50',
  props.size === 'sm' ? 'min-h-8 px-3 py-1.5 text-xs' : props.size === 'lg' ? 'min-h-12 px-5 py-3 text-base' : 'min-h-[42px] px-4 py-2.5 text-sm',
  props.variant === 'primary' ? 'bg-primary-500 text-white hover:bg-primary-600' : props.variant === 'danger' ? 'bg-error-500 text-white hover:bg-error-700' : props.variant === 'secondary' ? 'border border-gray-300 text-gray-600 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600' : 'text-gray-600 hover:bg-gray-50',
  attrs.class,
])
const forwarded = computed(() => { const { class: ignored, disabled, ...rest } = attrs; return rest })
</script>
<template><button v-bind="forwarded" :class="classes" :disabled="Boolean(attrs.disabled) || loading"><span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"/><slot/></button></template>
