<script setup lang="ts">
import { computed } from 'vue'
import { Circle } from '@lucide/vue'

const props = defineProps<{ modelValue: number | string; value: number | string; name: string; disabled?: boolean }>()
defineEmits<{ 'update:modelValue': [value: number | string] }>()
const selected = computed(() => props.modelValue === props.value)
const labelClasses = computed(() => props.disabled
  ? 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-500 hover:border-gray-200 hover:bg-gray-50'
  : selected.value ? 'border-primary-500 bg-primary-50 text-primary-600' : '')
const indicatorClasses = computed(() => props.disabled
  ? selected.value ? 'border-gray-400 bg-gray-100 text-gray-600' : 'border-gray-300 bg-gray-100 text-transparent'
  : selected.value ? 'border-primary-500 bg-white text-primary-500' : 'border-gray-400 bg-white text-transparent')
</script>

<template>
  <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-100 p-3 text-sm text-gray-600 transition hover:border-primary-200 hover:bg-primary-25 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2" :class="labelClasses">
    <input class="sr-only" type="radio" :name="name" :value="value" :checked="selected" :disabled="disabled" @change="$emit('update:modelValue', value)">
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border transition" :class="indicatorClasses"><Circle :size="10" fill="currentColor" /></span>
    <span><slot /></span>
  </label>
</template>
