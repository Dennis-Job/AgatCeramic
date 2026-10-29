<script setup lang="ts">
import type { SiteLink } from '~/types/homePage'

defineProps<{
  phone?: string
  phoneHref?: string
  email?: string | null
  note?: string
  socialLinks?: SiteLink[]
}>()
defineEmits<{ navigate: [] }>()
</script>

<template>
  <div
    v-if="phone || email || note || socialLinks?.length"
    class="menu-footer"
    role="group"
    aria-label="Контакты в меню"
  >
    <a v-if="phone" :href="phoneHref">{{ phone }}</a>
    <a v-if="email" :href="`mailto:${email}`">{{ email }}</a>
    <p v-if="note" class="menu-footer__note">{{ note }}</p>
    <nav
      v-if="socialLinks?.length"
      class="menu-footer__socials"
      aria-label="Соцсети в меню"
    >
      <NuxtLink
        v-for="(link, index) in socialLinks"
        :key="index"
        :to="link.to"
        @click="$emit('navigate')"
        >{{ link.label }}</NuxtLink
      >
    </nav>
  </div>
</template>

<style scoped>
.menu-footer {
  display: flex;
  flex-shrink: 0;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  overflow-wrap: anywhere;
}
.menu-footer a {
  max-width: 100%;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
  transition: color 0.3s;
}
.menu-footer a:hover {
  color: var(--color-ink);
}
.menu-footer__note {
  max-width: 100%;
  margin: 6px 0 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.7;
  white-space: pre-line;
}
.menu-footer__socials {
  display: flex;
  max-width: 100%;
  flex-wrap: wrap;
  gap: 12px 20px;
  margin-top: 16px;
}
.menu-footer__socials a {
  border-bottom: 1px solid var(--color-line);
  padding-bottom: 4px;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}
</style>
