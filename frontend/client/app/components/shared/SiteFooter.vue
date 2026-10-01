<script setup lang="ts">
import type { HomePageContent, SiteLink } from '~/types/homePage'

defineProps<{
  content?: HomePageContent['footer']
  navigation?: SiteLink[]
  logoUrl?: string
  logoAlt?: string
}>()
</script>

<template>
  <footer class="site-footer">
    <div class="container">
      <div class="site-footer__grid">
        <div class="site-footer__brand">
          <NuxtLink
            to="/#home"
            class="site-footer__logo"
            aria-label="AgatCeramic — на главную"
          >
            <img
              v-if="logoUrl"
              :src="logoUrl"
              :alt="logoAlt || 'AgatCeramic'"
              width="240"
              height="48"
            />
            <template v-else>AGAT<span>CERAMIC</span><sup>°</sup></template>
          </NuxtLink>
          <p v-if="content?.tagline">{{ content.tagline }}</p>
        </div>
        <div v-if="navigation?.length" class="site-footer__column">
          <h2>Навигация</h2>
          <NuxtLink
            v-for="link in navigation ?? []"
            :key="link.to"
            :to="link.to"
            >{{ link.label }}</NuxtLink
          >
        </div>
        <div v-if="content?.exploreLinks.length" class="site-footer__column">
          <h2>Исследовать</h2>
          <NuxtLink
            v-for="link in content?.exploreLinks ?? []"
            :key="link.to"
            :to="link.to"
            >{{ link.label }}</NuxtLink
          >
        </div>
        <div v-if="content?.message" class="site-footer__message">
          <span v-if="content?.message.eyebrow" class="eyebrow">{{
            content.message.eyebrow
          }}</span>
          <p v-if="content?.message.text">{{ content.message.text }}</p>
          <NuxtLink
            v-if="content?.message.linkUrl"
            :to="content.message.linkUrl"
          >
            {{ content.message.linkLabel }} <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </div>
      <div
        v-if="content?.bottomLeft || content?.bottomRight"
        class="site-footer__bottom"
      >
        <span v-if="content?.bottomLeft">{{ content.bottomLeft }}</span>
        <span v-if="content?.bottomRight">{{ content.bottomRight }}</span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  border-top: 1px solid var(--color-line);
  padding: 80px 0 40px;
  background: var(--color-bg-soft);
}
.site-footer__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1fr) minmax(
      0,
      1.2fr
    );
  overflow-wrap: anywhere;
  gap: 40px;
  margin-bottom: 70px;
}
.site-footer__logo {
  display: inline-block;
  margin-bottom: 18px;
  font-size: 19px;
  font-weight: 600;
  letter-spacing: 0.19em;
  white-space: nowrap;
}
.site-footer__logo img {
  display: block;
  width: min(240px, 60vw);
  height: 48px;
  object-fit: contain;
  object-position: left center;
}
.site-footer__logo span {
  font-weight: 400;
}
.site-footer__logo sup {
  position: relative;
  top: -0.4em;
  margin-left: 3px;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 400;
  letter-spacing: 0;
}
.site-footer__brand p {
  margin: 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.8;
}
.site-footer__column h2 {
  margin: 0 0 22px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}
.site-footer__column a {
  display: block;
  width: max-content;
  max-width: 100%;
  margin-bottom: 12px;
  color: var(--color-muted);
  font-size: 13px;
  transition:
    color 0.25s,
    transform 0.25s;
}
.site-footer__column a:hover {
  color: var(--color-ink);
  transform: translateX(4px);
}
.site-footer__message p {
  max-width: 230px;
  margin: 18px 0 22px;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
}
.site-footer__message a {
  display: inline-block;
  border-bottom: 1px solid var(--color-ink);
  padding-bottom: 7px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.site-footer__bottom {
  display: flex;
  overflow-wrap: anywhere;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--color-line);
  padding-top: 28px;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.05em;
}
@media (max-width: 900px) {
  .site-footer__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .site-footer {
    padding-top: 64px;
  }
  .site-footer__grid {
    gap: 42px 20px;
  }
  .site-footer__brand,
  .site-footer__message {
    grid-column: 1 / -1;
  }
  .site-footer__bottom {
    flex-direction: column;
  }
}
</style>
