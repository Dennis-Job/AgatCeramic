<script setup lang="ts">
import UiTextarea from './ui/UiTextarea.vue'

defineOptions({ inheritAttrs: false })

withDefaults(defineProps<{ modelValue?: string | null }>(), { modelValue: '' })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <UiTextarea v-bind="$attrs" :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" />
</template>
