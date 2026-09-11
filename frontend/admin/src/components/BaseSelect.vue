<script setup lang="ts">
import UiSelect from './ui/UiSelect.vue'
type SelectOption = { label: string; value: string }
withDefaults(defineProps<{ modelValue: string; options: SelectOption[]; placeholder?: string; accessibleName: string; searchable?: boolean; searchPlaceholder?: string; clearable?: boolean; teleportMenu?: boolean; disabled?: boolean }>(), { placeholder: 'Выберите значение', searchable: false, searchPlaceholder: 'Начните вводить для поиска', clearable: false, teleportMenu: false, disabled: false })
defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
</script>
<template><UiSelect v-bind="$props" @update:model-value="$emit('update:modelValue', $event)" @change="$emit('change', $event)" /></template>
