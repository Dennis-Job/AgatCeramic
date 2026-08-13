<script setup lang="ts">
import { Check } from '@lucide/vue'

const props = defineProps<{
  modelValue: Array<number | string>
  value: number | string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: Array<number | string>] }>()

function toggle(): void {
  emit(
    'update:modelValue',
    props.modelValue.includes(props.value)
      ? props.modelValue.filter((item) => item !== props.value)
      : [...props.modelValue, props.value],
  )
}
</script>

<template>
  <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-100 p-3 text-sm text-gray-600 transition hover:border-primary-200 hover:bg-primary-25" :class="{ 'border-primary-500 bg-primary-50 text-primary-600': modelValue.includes(value) }">
    <input class="sr-only" type="checkbox" :checked="modelValue.includes(value)" @change="toggle" />
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border transition" :class="modelValue.includes(value) ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-400 bg-white text-transparent'">
      <Check :size="14" :stroke-width="3" />
    </span>
    <span><slot /></span>
  </label>
</template>
