<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
</script>

<template>
  <section class="home-promo" aria-labelledby="home-promo-title">
    <div class="container">
      <span class="eyebrow">AgatCeramic</span>
      <h2 id="home-promo-title">Пространство начинается с поверхности.</h2>
      <p>Исследуйте материалы, формы и оттенки, чтобы найти своё сочетание.</p>
      <UiButton to="/#catalog" variant="light"
        >Выбрать направление <span aria-hidden="true">→</span></UiButton
      >
    </div>
  </section>
</template>

<style scoped>
.home-promo {
  padding: 96px 0;
  background: var(--color-ink);
  color: var(--color-white);
  text-align: center;
}
.home-promo .eyebrow {
  color: var(--color-on-dark-eyebrow);
}
.home-promo h2 {
  margin: 18px 0 14px;
  font-size: clamp(28px, 3.4vw, 46px);
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.15;
}
.home-promo p {
  margin: 0 0 40px;
  color: var(--color-on-dark-muted);
  font-size: 14px;
  line-height: 1.7;
}
@media (max-width: 760px) {
  .home-promo {
    padding: 76px 0;
  }
}
</style>
