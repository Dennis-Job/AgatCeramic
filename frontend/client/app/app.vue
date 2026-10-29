<script setup lang="ts">
import CartHost from '~/features/cart/components/CartHost.vue'
</script>

<template>
  <NuxtRouteAnnouncer />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
  <UiSuccessNotification />
  <CartHost />
</template>
