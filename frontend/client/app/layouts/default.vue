<script setup lang="ts">
import SiteHeader from '~/components/shared/SiteHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
import type { HomePageContent } from '~/types/homePage'

defineProps<{ homePage?: HomePageContent | null }>()
</script>

<template>
  <SiteHeader :content="homePage?.header" :tagline="homePage?.footer.tagline" />
  <main>
    <slot />
  </main>
  <SiteFooter
    :content="homePage?.footer"
    :navigation="homePage?.header.navigation"
    :logo-url="homePage?.header.logoUrl"
    :logo-alt="homePage?.header.logoAlt"
  />
</template>
