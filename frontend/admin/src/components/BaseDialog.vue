<script setup lang="ts">
import UiDialog from './ui/UiDialog.vue'
withDefaults(defineProps<{ open: boolean; labelledby: string; describedby?: string; closeDisabled?: boolean; suspended?: boolean; overlayClass?: string; panelClass?: string }>(), { describedby: undefined, closeDisabled: false, suspended: false, overlayClass: 'z-50 grid place-items-center p-4', panelClass: '' })
defineEmits<{ close: [] }>()
</script>
<template><UiDialog v-bind="$props" @close="$emit('close')"><slot /></UiDialog></template>
