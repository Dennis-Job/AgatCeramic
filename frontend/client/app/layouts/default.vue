<script setup lang="ts">
import SiteHeader from '~/components/shared/SiteHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
const { data: appearance } = await useSiteAppearance()
</script>

<template>
  <SiteHeader
    :content="appearance?.header"
    :tagline="appearance?.footer.tagline"
  />
  <main>
    <slot />
  </main>
  <SiteFooter
    :content="appearance?.footer"
    :navigation="appearance?.header.navigation"
    :logo-url="appearance?.header.logoUrl"
    :logo-alt="appearance?.header.logoAlt"
  />
</template>
