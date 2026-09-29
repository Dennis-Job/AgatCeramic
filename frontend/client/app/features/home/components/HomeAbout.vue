<script setup lang="ts">
import type { HomePageContent } from '~/types/homePage'

defineProps<{ content: HomePageContent['about'] }>()
</script>

<template>
  <section id="about" class="home-about" aria-labelledby="home-about-title">
    <div class="home-about__copy">
      <span class="eyebrow">{{ content.eyebrow }}</span>
      <h2 id="home-about-title">{{ content.title }}</h2>
      <p>{{ content.description }}</p>
      <NuxtLink
        v-if="content.linkUrl"
        :to="content.linkUrl"
        class="home-about__link"
      >
        {{ content.linkLabel }} <span aria-hidden="true">→</span>
      </NuxtLink>
    </div>
    <div class="home-about__image">
      <img
        v-if="content.image"
        :src="content.image"
        :alt="content.imageAlt"
        width="1280"
        height="853"
        loading="lazy"
      />
    </div>
  </section>
</template>

<style scoped>
.home-about {
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  scroll-margin-top: 78px;
  background: var(--color-beige);
}
.home-about__copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  padding: clamp(48px, 6vw, 96px);
}
.home-about h2 {
  margin: 14px 0 26px;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.16;
}
.home-about p {
  max-width: 480px;
  margin: 0 0 36px;
  color: var(--color-ink-soft);
  font-size: 14px;
  line-height: 1.9;
}
.home-about__link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--color-ink);
  padding-bottom: 8px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}
.home-about__image {
  min-height: 450px;
  overflow: hidden;
  background: var(--color-beige);
}
.home-about__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 1.2s var(--ease-out);
}
.home-about__image:hover img {
  transform: scale(1.05);
}
@media (max-width: 900px) {
  .home-about {
    grid-template-columns: 1fr;
  }
  .home-about__copy {
    padding: 64px var(--page-gutter);
  }
  .home-about__image {
    height: 48vw;
    min-height: 350px;
  }
}
</style>
