<script setup lang="ts">
import UiRadio from './ui/UiRadio.vue'
defineProps<{
  modelValue: number | string
  value: number | string
  name: string
  disabled?: boolean
}>()

defineEmits<{ 'update:modelValue': [value: number | string] }>()
</script>

<template>
  <UiRadio v-bind="$props" @update:model-value="$emit('update:modelValue', $event)"><slot /></UiRadio>
</template>
