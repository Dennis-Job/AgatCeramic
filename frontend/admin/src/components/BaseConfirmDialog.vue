<script setup lang="ts">
import ConfirmDialog from './shared/ConfirmDialog.vue'
withDefaults(defineProps<{ open: boolean; title: string; description: string; confirmLabel?: string; busy?: boolean; error?: string }>(), { confirmLabel: 'Удалить', busy: false, error: '' })
defineEmits<{ close: []; confirm: [] }>()
</script>
<template><ConfirmDialog v-bind="$props" @close="$emit('close')" @confirm="$emit('confirm')" /></template>
