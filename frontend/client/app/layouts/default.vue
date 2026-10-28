<script setup lang="ts">
import StorefrontHeader from '~/features/callback/components/StorefrontHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
import { searchNavigation } from '~/utils/navigation'
const { data: appearance } = await useSiteAppearance()
const footerNavigation = computed(() =>
  searchNavigation(appearance.value?.header.navigation ?? [], ''),
)
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
    :navigation="footerNavigation"
    :logo-url="appearance?.header.logoUrl"
    :logo-alt="appearance?.header.logoAlt"
  />
</template>
