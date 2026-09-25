<script setup lang="ts">
import { Menu, Search, X } from '@lucide/vue'
import { siteNavigation } from '~/config/siteNavigation'

type Panel = 'menu' | 'search' | null

const route = useRoute()
const panel = ref<Panel>(null)
const searchQuery = ref('')
const menuTrigger = ref<HTMLButtonElement | null>(null)
const menuPanel = ref<HTMLElement | null>(null)
const searchPanel = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)

const searchResults = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase('ru')
  return query
    ? siteNavigation.filter((link) =>
        link.label.toLocaleLowerCase('ru').includes(query),
      )
    : siteNavigation
})

function closePanel() {
  panel.value = null
}

function openPanel(nextPanel: Exclude<Panel, null>) {
  searchQuery.value = ''
  panel.value = nextPanel
}

function trapFocus(event: KeyboardEvent) {
  const activePanel =
    panel.value === 'menu' ? menuPanel.value : searchPanel.value
  if (!activePanel) return

  const focusable = Array.from(
    activePanel.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled])',
    ),
  )
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}

function onKeydown(event: KeyboardEvent) {
  if (!panel.value) return
  if (event.key === 'Escape') closePanel()
  if (event.key === 'Tab') trapFocus(event)
}

watch(panel, async (next, previous) => {
  if (!import.meta.client) return
  document.body.classList.toggle('has-overlay', next !== null)
  await nextTick()
  if (next === 'menu')
    menuPanel.value?.querySelector<HTMLElement>('button')?.focus()
  if (next === 'search') searchInput.value?.focus()
  if (!next && previous === 'menu') menuTrigger.value?.focus()
  if (!next && previous === 'search') menuTrigger.value?.focus()
})

watch(() => route.fullPath, closePanel)

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('has-overlay')
})
</script>

<template>
  <div class="topbar">
    <div class="container topbar__inner">
      <span>Керамика. Пространство. Детали.</span>
      <span>Керамогранит · Плитка · Мозаика</span>
    </div>
  </div>

  <header class="site-header">
    <div class="container site-header__inner">
      <NuxtLink
        to="/#home"
        class="wordmark"
        aria-label="AgatCeramic — на главную"
      >
        AGAT<span>CERAMIC</span><sup>°</sup>
      </NuxtLink>

      <nav class="site-nav" aria-label="Основная навигация">
        <NuxtLink
          v-for="link in siteNavigation"
          :key="link.to"
          :to="link.to"
          :aria-current="
            route.hash === link.to.slice(1) ||
            (!route.hash && link.to === '/#home')
              ? 'page'
              : undefined
          "
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <div class="site-header__actions">
        <button
          ref="menuTrigger"
          class="icon-button"
          type="button"
          aria-label="Открыть меню"
          :aria-expanded="panel === 'menu'"
          aria-controls="site-menu"
          @click="openPanel('menu')"
        >
          <Menu :size="22" :stroke-width="1.6" aria-hidden="true" />
        </button>
      </div>
    </div>
  </header>

  <button
    v-if="panel"
    class="backdrop"
    type="button"
    tabindex="-1"
    aria-label="Закрыть панель"
    @click="closePanel"
  />

  <aside
    v-if="panel === 'menu'"
    id="site-menu"
    ref="menuPanel"
    class="menu-panel"
    role="dialog"
    aria-modal="true"
    aria-label="Меню сайта"
  >
    <div class="menu-panel__top">
      <span class="wordmark wordmark--small"
        >AGAT<span>CERAMIC</span><sup>°</sup></span
      >
      <button
        class="close-button"
        type="button"
        aria-label="Закрыть меню"
        @click="closePanel"
      >
        <X :size="18" :stroke-width="1.6" aria-hidden="true" />
      </button>
    </div>
    <nav class="menu-panel__nav" aria-label="Мобильная навигация">
      <NuxtLink
        v-for="(link, index) in siteNavigation"
        :key="link.to"
        :to="link.to"
        @click="closePanel"
      >
        {{ link.label }} <span>{{ String(index + 1).padStart(2, '0') }}</span>
      </NuxtLink>
    </nav>
    <button
      class="menu-panel__search"
      type="button"
      aria-controls="site-search"
      @click="openPanel('search')"
    >
      <Search :size="18" :stroke-width="1.6" aria-hidden="true" />
      Поиск по разделам страницы
    </button>
    <p class="menu-panel__note">
      AgatCeramic · Материалы для вашего пространства
    </p>
  </aside>

  <div
    v-if="panel === 'search'"
    id="site-search"
    ref="searchPanel"
    class="search-panel"
    role="dialog"
    aria-modal="true"
    aria-label="Поиск по разделам страницы"
  >
    <div class="container">
      <div class="search-panel__top">
        <span class="eyebrow">Поиск по разделам страницы</span>
        <button
          class="close-button"
          type="button"
          aria-label="Закрыть поиск"
          @click="closePanel"
        >
          <X :size="18" :stroke-width="1.6" aria-hidden="true" />
        </button>
      </div>
      <label class="visually-hidden" for="section-search"
        >Название раздела</label
      >
      <input
        id="section-search"
        ref="searchInput"
        v-model="searchQuery"
        type="search"
        placeholder="Например: материалы"
        autocomplete="off"
      />
      <nav class="search-panel__results" aria-label="Найденные разделы">
        <NuxtLink
          v-for="link in searchResults"
          :key="link.to"
          :to="link.to"
          @click="closePanel"
        >
          {{ link.label }} <span aria-hidden="true">→</span>
        </NuxtLink>
        <p v-if="!searchResults.length" role="status">
          Раздел не найден. Попробуйте другое название.
        </p>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.topbar {
  border-bottom: 1px solid var(--color-line);
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.08em;
}
.topbar__inner {
  display: flex;
  min-height: 40px;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}
.site-header {
  position: sticky;
  z-index: 50;
  top: 0;
  background: var(--color-header-surface);
  border-bottom: 1px solid var(--color-line);
  backdrop-filter: blur(12px);
}
.site-header__inner {
  display: flex;
  min-height: 78px;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}
.wordmark {
  flex: 0 0 auto;
  font-size: 19px;
  font-weight: 600;
  letter-spacing: 0.19em;
  white-space: nowrap;
}
.wordmark span {
  font-weight: 400;
}
.wordmark sup {
  position: relative;
  top: -0.4em;
  margin-left: 3px;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 400;
  letter-spacing: 0;
}
.wordmark--small {
  font-size: 16px;
}
.site-nav {
  display: flex;
  gap: clamp(18px, 3vw, 44px);
}
.site-nav a {
  position: relative;
  padding-block: 8px;
  font-size: 13px;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.site-nav a::after {
  position: absolute;
  right: 0;
  bottom: 2px;
  left: 0;
  height: 1px;
  background: var(--color-ink);
  content: '';
  transform: scaleX(0);
  transform-origin: right;
  transition: transform 0.35s var(--ease-out);
}
.site-nav a:hover::after,
.site-nav a[aria-current]::after {
  transform: scaleX(1);
  transform-origin: left;
}
.site-header__actions {
  display: flex;
  align-items: center;
  gap: 4px;
}
.icon-button {
  display: grid;
  width: 44px;
  height: 44px;
  place-items: center;
  border: 0;
  border-radius: 50%;
  background: transparent;
  transition: background 0.25s;
}
.icon-button:hover {
  background: var(--color-beige);
}
.backdrop {
  position: fixed;
  z-index: 90;
  inset: 0;
  width: 100%;
  border: 0;
  background: var(--color-overlay);
  backdrop-filter: blur(3px);
}
.menu-panel,
.search-panel {
  position: fixed;
  z-index: 100;
  background: var(--color-bg-soft);
  box-shadow: 0 20px 60px var(--color-shadow-soft);
}
.menu-panel {
  display: flex;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(480px, 100%);
  flex-direction: column;
  overflow-y: auto;
  padding: 36px 48px;
}
.menu-panel__top,
.search-panel__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}
.menu-panel__top {
  margin-bottom: 56px;
}
.close-button {
  display: grid;
  width: 44px;
  height: 44px;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--color-line);
  border-radius: 50%;
  background: transparent;
  transition:
    background 0.25s,
    color 0.25s;
}
.close-button:hover {
  background: var(--color-ink);
  color: var(--color-white);
}
.menu-panel__nav {
  display: flex;
  flex-direction: column;
}
.menu-panel__nav a {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--color-line);
  padding: 14px 0;
  font-size: clamp(26px, 3vw, 34px);
  font-weight: 500;
  letter-spacing: -0.01em;
}
.menu-panel__nav a:hover {
  color: var(--color-muted);
}
.menu-panel__nav span {
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.2em;
}
.menu-panel__search {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  margin-top: 28px;
  border: 0;
  border-bottom: 1px solid var(--color-line);
  padding: 14px 0;
  background: transparent;
  font-size: 13px;
  text-align: left;
}
.menu-panel__search:hover {
  color: var(--color-muted);
}
.menu-panel__note {
  margin-top: auto;
  padding-top: 56px;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.7;
}
.search-panel {
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  overflow-y: auto;
  padding: 40px 0 80px;
  background: var(--color-bg);
}
.search-panel__top {
  margin-bottom: 60px;
}
.search-panel input {
  width: 100%;
  border: 0;
  border-bottom: 1px solid var(--color-ink);
  border-radius: 0;
  padding: 14px 0;
  outline: 0;
  background: transparent;
  color: var(--color-ink);
  font-size: clamp(26px, 4vw, 44px);
  font-weight: 500;
  letter-spacing: -0.01em;
}
.search-panel input:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 5px;
}
.search-panel__results {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 32px;
  margin-top: 56px;
}
.search-panel__results a {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--color-line);
  padding: 18px 0;
  font-size: 16px;
}
.search-panel__results a:hover {
  color: var(--color-muted);
}
.search-panel__results p {
  color: var(--color-muted);
  font-size: 14px;
}
@media (max-width: 1024px) {
  .site-nav {
    display: none;
  }
}
@media (max-width: 760px) {
  .topbar__inner span:first-child {
    display: none;
  }
  .site-header__inner {
    min-height: 68px;
  }
  .wordmark {
    font-size: 15px;
    letter-spacing: 0.13em;
  }
  .menu-panel {
    padding: 24px 20px;
  }
  .search-panel {
    padding-top: 24px;
  }
  .search-panel__top {
    margin-bottom: 44px;
  }
  .search-panel__results {
    grid-template-columns: 1fr;
  }
}
</style>
