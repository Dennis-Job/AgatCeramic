<script setup lang="ts">
import StorefrontHeader from '~/features/callback/components/StorefrontHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
const { data: appearance } = await useSiteAppearance()
</script>

<template>
  <StorefrontHeader
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
