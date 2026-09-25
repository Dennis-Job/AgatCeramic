<script setup lang="ts">
import { siteNavigation } from '~/config/siteNavigation'
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
            AGAT<span>CERAMIC</span><sup>°</sup>
          </NuxtLink>
          <p>Керамогранит · Плитка · Мозаика</p>
        </div>
        <div class="site-footer__column">
          <h2>Навигация</h2>
          <NuxtLink
            v-for="link in siteNavigation"
            :key="link.to"
            :to="link.to"
            >{{ link.label }}</NuxtLink
          >
        </div>
        <div class="site-footer__column">
          <h2>Исследовать</h2>
          <NuxtLink to="/#catalog">Направления</NuxtLink>
          <NuxtLink to="/#materials">Фактуры и формы</NuxtLink>
          <NuxtLink to="/#guide">Советы по выбору</NuxtLink>
        </div>
        <div class="site-footer__message">
          <span class="eyebrow">AgatCeramic</span>
          <p>
            Поверхности, которые помогают создать пространство с характером.
          </p>
          <NuxtLink to="/#catalog"
            >Смотреть направления <span aria-hidden="true">→</span></NuxtLink
          >
        </div>
      </div>
      <div class="site-footer__bottom">
        <span>© 2026 AgatCeramic</span>
        <span>Керамогранит · Плитка · Мозаика</span>
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
  grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
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
