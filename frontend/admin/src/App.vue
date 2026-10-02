<script setup lang="ts">
import AdminLayout from './layouts/AdminLayout.vue'
import AuthLayout from './layouts/AuthLayout.vue'
import UiNotificationHost from './components/ui/UiNotificationHost.vue'
</script>

<template>
  <UiNotificationHost />
  <RouterView v-slot="{ Component, route }">
    <AdminLayout v-if="route.meta.requiresAuth">
      <component :is="Component" />
    </AdminLayout>
    <AuthLayout v-else-if="route.meta.guestOnly">
      <component :is="Component" />
    </AuthLayout>
    <component :is="Component" v-else />
  </RouterView>
</template>
