<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from './stores/auth'
import AdminLayout from './layouts/AdminLayout.vue'
import AuthLayout from './layouts/AuthLayout.vue'
import UiNotificationHost from './components/ui/UiNotificationHost.vue'
const auth = useAuthStore()
const importSessionKey = computed(
  () => `${auth.user?.id}:${auth.user?.permissions?.join(',')}`,
)
</script>

<template>
  <UiNotificationHost />
  <RouterView v-slot="{ Component, route }">
    <AdminLayout v-if="route.meta.requiresAuth">
      <KeepAlive
        :key="importSessionKey"
        :include="[
          'ProductImportView',
          'ProductPriceStatusImportView',
          'ProductGroupImportView',
        ]"
      >
        <component :is="Component" />
      </KeepAlive>
    </AdminLayout>
    <AuthLayout v-else-if="route.meta.guestOnly">
      <component :is="Component" />
    </AuthLayout>
    <component :is="Component" v-else />
  </RouterView>
</template>
