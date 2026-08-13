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
  <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-[#eaecf0] p-3 text-sm text-[#475467] transition hover:border-[#bdb4fe] hover:bg-[#fcfbff]" :class="{ 'border-[#7f56d9] bg-[#f4f3ff] text-[#6941c6]': modelValue === value }">
    <input class="sr-only" type="radio" :name="name" :value="value" :checked="modelValue === value" @change="emit('update:modelValue', value)" />
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border transition" :class="modelValue === value ? 'border-[#7f56d9] bg-white text-[#7f56d9]' : 'border-[#98a2b3] bg-white text-transparent'">
      <Circle :size="10" fill="currentColor" />
    </span>
    <span><slot /></span>
  </label>
</template>
