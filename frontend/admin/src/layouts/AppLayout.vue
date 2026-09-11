<script setup lang="ts">
// Compatibility adapter for consumers that still import the former layout path.
import AdminLayout from './AdminLayout.vue'
</script>

<template>
  <AdminLayout><slot /></AdminLayout>
</template>
