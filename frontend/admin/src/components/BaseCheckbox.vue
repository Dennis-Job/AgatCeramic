<script setup lang="ts">
import { computed } from 'vue'
import { Check } from '@lucide/vue'

const props = defineProps<{
  modelValue?: Array<number | string>
  value?: number | string
  checked?: boolean
  mode?: 'boolean'
  accessibleName?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: Array<number | string>]; 'update:checked': [value: boolean] }>()

const isBooleanMode = computed(() => props.mode === 'boolean')
const isChecked = computed(() => isBooleanMode.value ? props.checked : props.modelValue?.includes(props.value as number | string))

function toggle(): void {
  if (isBooleanMode.value) {
    emit('update:checked', !props.checked)

    return
  }

  const modelValue = props.modelValue ?? []
  const value = props.value as number | string

  emit(
    'update:modelValue',
    modelValue.includes(value)
      ? modelValue.filter((item) => item !== value)
      : [...modelValue, value],
  )
}
</script>

<template>
  <label class="flex min-h-[42px] cursor-pointer items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm text-gray-600 transition hover:border-primary-200 hover:bg-primary-25 focus-within:border-primary-500 focus-within:ring-4 focus-within:ring-primary-50" :class="{ 'border-primary-500 bg-primary-50 text-primary-600': isChecked }">
    <input class="sr-only" type="checkbox" :checked="isChecked" :aria-label="accessibleName" @change="toggle" />
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border transition" :class="isChecked ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-400 bg-white text-transparent'">
      <Check :size="14" :stroke-width="3" />
    </span>
    <span class="min-w-0 break-words [overflow-wrap:anywhere]"><slot /></span>
  </label>
</template>
