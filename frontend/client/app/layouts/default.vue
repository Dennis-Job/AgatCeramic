<script setup lang="ts">
import SiteHeader from '~/components/shared/SiteHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
</script>

<template>
  <SiteHeader />
  <main>
    <slot />
  </main>
  <SiteFooter />
</template>
