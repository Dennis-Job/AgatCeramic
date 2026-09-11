<script setup lang="ts">
import UiAlert from './ui/UiAlert.vue'
withDefaults(defineProps<{ tone?: 'error' | 'success'; live?: 'assertive' | 'polite' }>(), { tone: 'error', live: 'assertive' })
</script>
<template><UiAlert :tone="tone" :live="live"><slot /></UiAlert></template>
