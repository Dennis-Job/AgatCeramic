<script setup lang="ts">
import AppLayout from './layouts/AppLayout.vue'
</script>

<template>
  <RouterView v-slot="{ Component, route }">
    <AppLayout v-if="route.meta.requiresAuth">
      <component :is="Component" />
    </AppLayout>
    <component :is="Component" v-else />
  </RouterView>
</template>
