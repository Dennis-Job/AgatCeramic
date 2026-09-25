<script setup lang="ts">
import { materialGuide } from '../data/homeContent'
import SectionHeading from '~/components/shared/SectionHeading.vue'
</script>

<template>
  <section
    id="guide"
    class="section home-guide"
    aria-labelledby="home-guide-title"
  >
    <div class="container">
      <SectionHeading
        title-id="home-guide-title"
        eyebrow="Детали выбора"
        title="На что обратить внимание"
      />
      <div class="home-guide__grid">
        <article
          v-for="(item, index) in materialGuide"
          :key="item.title"
          class="home-guide__item"
        >
          <span>{{ String(index + 1).padStart(2, '0') }}</span>
          <h3>{{ item.title }}</h3>
          <p>{{ item.description }}</p>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.home-guide__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 40px;
}
.home-guide__item {
  border-top: 1px solid var(--color-line);
  padding-top: 28px;
}
.home-guide__item span {
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.2em;
}
.home-guide__item h3 {
  margin: 16px 0 10px;
  font-size: 16px;
  font-weight: 500;
}
.home-guide__item p {
  margin: 0;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
}
@media (max-width: 900px) {
  .home-guide__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .home-guide__grid {
    grid-template-columns: 1fr;
    gap: 32px;
  }
}
</style>
