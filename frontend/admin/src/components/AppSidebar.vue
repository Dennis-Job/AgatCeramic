<script setup lang="ts">
// Compatibility adapter for consumers that still import the former component path.
import AdminSidebar from '../layouts/components/AdminSidebar.vue'

defineProps<{ isOpen: boolean }>()
defineEmits<{ close: [] }>()
</script>

<template>
  <AdminSidebar :is-open="isOpen" @close="$emit('close')" />
</template>
