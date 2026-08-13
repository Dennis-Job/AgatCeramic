<script setup lang="ts">
import { Circle } from '@lucide/vue'

defineProps<{
  modelValue: number | string
  value: number | string
  name: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number | string] }>()
</script>

<template>
  <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-100 p-3 text-sm text-gray-600 transition hover:border-primary-200 hover:bg-primary-25" :class="{ 'border-primary-500 bg-primary-50 text-primary-600': modelValue === value }">
    <input class="sr-only" type="radio" :name="name" :value="value" :checked="modelValue === value" @change="emit('update:modelValue', value)" />
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border transition" :class="modelValue === value ? 'border-primary-500 bg-white text-primary-500' : 'border-gray-400 bg-white text-transparent'">
      <Circle :size="10" fill="currentColor" />
    </span>
    <span><slot /></span>
  </label>
</template>
