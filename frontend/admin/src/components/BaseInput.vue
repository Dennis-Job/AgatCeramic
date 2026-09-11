<script setup lang="ts">
import UiInput from './ui/UiInput.vue'
defineOptions({ inheritAttrs: false })
withDefaults(defineProps<{ modelValue?: string; type?: string; searchable?: boolean }>(), { modelValue: '', type: 'text', searchable: false })
defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <UiInput v-bind="$attrs" :model-value="modelValue" :type="type" :searchable="searchable" @update:model-value="$emit('update:modelValue', $event)" />
</template>
