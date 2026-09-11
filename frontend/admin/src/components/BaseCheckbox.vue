<script setup lang="ts">
import UiCheckbox from './ui/UiCheckbox.vue'
defineProps<{
  modelValue?: Array<number | string>
  value?: number | string
  checked?: boolean
  mode?: 'boolean'
  accessibleName?: string
  disabled?: boolean
}>()

defineEmits<{ 'update:modelValue': [value: Array<number | string>]; 'update:checked': [value: boolean] }>()
</script>

<template>
  <UiCheckbox v-bind="$props" @update:model-value="$emit('update:modelValue', $event)" @update:checked="$emit('update:checked', $event)"><slot /></UiCheckbox>
</template>
