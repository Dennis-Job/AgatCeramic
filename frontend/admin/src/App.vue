<script setup lang="ts">
import { computed } from 'vue'
import { START_LOCATION, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import AdminLayout from './layouts/AdminLayout.vue'
import AuthLayout from './layouts/AuthLayout.vue'
import UiAlert from './components/ui/UiAlert.vue'
import UiButton from './components/ui/UiButton.vue'
import UiDialog from './components/ui/UiDialog.vue'
import UiNotificationHost from './components/ui/UiNotificationHost.vue'
import { failedRouteHref } from './router'

const auth = useAuthStore()
const router = useRouter()
const importSessionKey = computed(
  () => `${auth.user?.id}:${auth.user?.permissions?.join(',')}`,
)
const hasActiveRoute = computed(
  () => router.currentRoute.value !== START_LOCATION,
)

function dismissRouteError(): void {
  failedRouteHref.value = undefined
}

function reloadFailedRoute(): void {
  if (!failedRouteHref.value) return
  const destination = new URL(failedRouteHref.value, window.location.href)
  if (destination.href === window.location.href) window.location.reload()
  else window.location.assign(destination.href)
}
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
  <UiDialog
    :open="Boolean(failedRouteHref)"
    labelledby="route-load-error-title"
    :describedby="hasActiveRoute ? 'route-load-error-description' : undefined"
    :close-disabled="!hasActiveRoute"
    panel-class="w-full max-w-md overflow-auto p-6"
    @close="dismissRouteError"
  >
    <UiAlert id="route-load-error-title">Не удалось загрузить страницу</UiAlert>
    <p
      v-if="hasActiveRoute"
      id="route-load-error-description"
      class="mt-3 text-sm leading-6 text-gray-500"
    >
      При обновлении могут потеряться несохранённые изменения.
    </p>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <UiButton
        v-if="hasActiveRoute"
        type="button"
        variant="surface"
        @click="dismissRouteError"
        >Остаться на странице</UiButton
      >
      <UiButton type="button" @click="reloadFailedRoute"
        >Обновить страницу</UiButton
      >
    </div>
  </UiDialog>
</template>
