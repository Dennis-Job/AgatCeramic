<script setup lang="ts">import UiEmptyState from './ui/UiEmptyState.vue'; defineProps<{ label: string }>()</script>
<template><UiEmptyState :label="label"><slot /></UiEmptyState></template>
