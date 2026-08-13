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
  <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-[#eaecf0] p-3 text-sm text-[#475467] transition hover:border-[#bdb4fe] hover:bg-[#fcfbff]" :class="{ 'border-[#7f56d9] bg-[#f4f3ff] text-[#6941c6]': modelValue.includes(value) }">
    <input class="sr-only" type="checkbox" :checked="modelValue.includes(value)" @change="toggle" />
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border transition" :class="modelValue.includes(value) ? 'border-[#7f56d9] bg-[#7f56d9] text-white' : 'border-[#98a2b3] bg-white text-transparent'">
      <Check :size="14" :stroke-width="3" />
    </span>
    <span><slot /></span>
  </label>
</template>
