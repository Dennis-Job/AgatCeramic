<script setup lang="ts">
import { Menu, Search, ShoppingBag, X } from '@lucide/vue'
import UiCloseButton from '~/components/ui/UiCloseButton.vue'
import UiIconButton from '~/components/ui/UiIconButton.vue'
import UiWordmark from '~/components/ui/UiWordmark.vue'
import type { HomePageContent } from '~/types/homePage'

type Panel = 'menu' | 'search' | null

const props = defineProps<{
  content?: HomePageContent['header']
  tagline?: string
  currentPath?: string
  navigationLabel?: string
}>()
const navigation = computed(() => props.content?.navigation ?? [])

const route = useRoute()
function isCurrent(to: string): boolean {
  const [path, hash] = to.split('#')
  return (
    path === (props.currentPath ?? route.path) &&
    (!hash || `#${hash}` === route.hash || (!route.hash && hash === 'home'))
  )
}
const panel = ref<Panel>(null)
const searchQuery = ref('')
let panelTrigger: HTMLElement | null = null
const menuPanel = ref<HTMLElement | null>(null)
const searchPanel = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const header = ref<HTMLElement | null>(null)
const searchTop = ref(0)
const backdropPanel = ref<Exclude<Panel, null>>('menu')
const searchPosition = computed(() => ({
  '--search-top': `${searchTop.value}px`,
}))
let headerObserver: ResizeObserver | undefined
const leavingPanels = new Set<Element>()

function updateSearchPosition() {
  if (header.value)
    searchTop.value = header.value.getBoundingClientRect().bottom
}

const searchResults = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase('ru')
  return query
    ? navigation.value.filter((link) =>
        link.label.toLocaleLowerCase('ru').includes(query),
      )
    : navigation.value
})

function closePanel() {
  panel.value = null
}

function clearSearch() {
  searchQuery.value = ''
  searchInput.value?.focus({ preventScroll: true })
}

function openPanel(nextPanel: Exclude<Panel, null>, event: MouseEvent) {
  if (!panel.value) panelTrigger = event.currentTarget as HTMLElement
  updateSearchPosition()
  backdropPanel.value = nextPanel
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
  if (
    event.shiftKey &&
    (document.activeElement === first || document.activeElement === activePanel)
  ) {
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

function activatePanel(element: Element) {
  leavingPanels.delete(element)
  ;(element as HTMLElement).inert = false
}

function deactivatePanel(element: Element) {
  leavingPanels.add(element)
  ;(element as HTMLElement).inert = true
}

function finishPanelClose(element: Element) {
  leavingPanels.delete(element)
  if (panel.value || leavingPanels.size) return
  document.body.classList.remove('has-overlay')
  if (panelTrigger?.isConnected) panelTrigger.focus({ preventScroll: true })
}

watch(panel, async (next) => {
  if (!import.meta.client) return
  if (next) document.body.classList.add('has-overlay')
  await nextTick()
  if (next === 'menu') menuPanel.value?.focus({ preventScroll: true })
  if (next === 'search') searchPanel.value?.focus({ preventScroll: true })
})

watch(() => route.fullPath, closePanel)

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', updateSearchPosition)
  window.addEventListener('scroll', updateSearchPosition, { passive: true })
  headerObserver = new ResizeObserver(updateSearchPosition)
  if (header.value) headerObserver.observe(header.value)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', updateSearchPosition)
  window.removeEventListener('scroll', updateSearchPosition)
  headerObserver?.disconnect()
  document.body.classList.remove('has-overlay')
})
</script>

<template>
  <div
    v-if="$slots['topbar-right'] || content?.topbarRight"
    class="topbar"
    :inert="panel === 'search'"
    role="region"
    aria-label="Информация магазина"
  >
    <div class="container topbar__inner">
      <span v-if="content?.topbarRight" class="topbar__text">{{
        content.topbarRight
      }}</span>
      <slot name="topbar-right" />
    </div>
  </div>

  <header ref="header" class="site-header" :inert="panel === 'search'">
    <div class="container site-header__inner">
      <NuxtLink
        to="/#home"
        class="wordmark"
        aria-label="AgatCeramic — на главную"
      >
        <img
          v-if="content?.logoUrl"
          :src="content.logoUrl"
          :alt="content.logoAlt"
          width="240"
          height="48"
        />
        <UiWordmark v-else />
      </NuxtLink>

      <nav
        v-if="navigation.length"
        class="site-nav"
        :aria-label="navigationLabel || 'Основная навигация'"
      >
        <NuxtLink
          v-for="link in navigation"
          :key="link.to"
          :to="link.to"
          :aria-current="isCurrent(link.to) ? 'page' : undefined"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <div class="site-header__actions">
        <UiIconButton
          label="Открыть поиск"
          :expanded="panel === 'search'"
          controls="site-search"
          :disabled="!navigation.length"
          title="Поиск по разделам сайта"
          @click="openPanel('search', $event)"
        >
          <Search :size="19" :stroke-width="1.6" aria-hidden="true" />
        </UiIconButton>
        <UiIconButton
          label="Корзина"
          disabled
          title="Корзина будет доступна позже"
        >
          <ShoppingBag :size="19" :stroke-width="1.6" aria-hidden="true" />
        </UiIconButton>
        <UiIconButton
          v-if="navigation.length"
          label="Открыть меню"
          :expanded="panel === 'menu'"
          controls="site-menu"
          @click="openPanel('menu', $event)"
        >
          <Menu :size="22" :stroke-width="1.6" aria-hidden="true" />
        </UiIconButton>
      </div>
    </div>
  </header>

  <Transition name="ui-backdrop">
    <button
      v-if="panel"
      class="backdrop"
      :class="{ 'backdrop--search': backdropPanel === 'search' }"
      :style="searchPosition"
      type="button"
      tabindex="-1"
      aria-label="Закрыть панель"
      @click="closePanel"
    />
  </Transition>

  <Transition
    name="ui-drawer"
    @before-enter="activatePanel"
    @before-leave="deactivatePanel"
    @after-leave="finishPanelClose"
  >
    <div
      v-if="panel === 'menu'"
      id="site-menu"
      ref="menuPanel"
      class="menu-panel"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      aria-label="Меню сайта"
    >
      <div class="menu-panel__top">
        <span class="wordmark wordmark--small">
          <img
            v-if="content?.logoUrl"
            :src="content.logoUrl"
            :alt="content.logoAlt"
            width="200"
            height="40"
          />
          <UiWordmark v-else />
        </span>
        <UiCloseButton label="Закрыть меню" @click="closePanel" />
      </div>
      <nav class="menu-panel__nav" aria-label="Мобильная навигация">
        <NuxtLink
          v-for="(link, index) in navigation"
          :key="link.to"
          :to="link.to"
          :aria-current="isCurrent(link.to) ? 'page' : undefined"
          :style="{ '--menu-link-index': index }"
          @click="closePanel"
        >
          {{ link.label }} <span>{{ String(index + 1).padStart(2, '0') }}</span>
        </NuxtLink>
      </nav>
      <button
        class="menu-panel__search"
        type="button"
        aria-haspopup="dialog"
        @click="openPanel('search', $event)"
      >
        <Search :size="18" :stroke-width="1.6" aria-hidden="true" />
        Поиск по разделам страницы
      </button>
      <p v-if="tagline" class="menu-panel__note">{{ tagline }}</p>
    </div>
  </Transition>

  <Transition
    name="ui-search"
    @before-enter="activatePanel"
    @before-leave="deactivatePanel"
    @after-leave="finishPanelClose"
  >
    <div
      v-if="panel === 'search'"
      id="site-search"
      ref="searchPanel"
      class="search-panel"
      :style="searchPosition"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      aria-label="Поиск по разделам страницы"
    >
      <div class="container">
        <div class="search-panel__row">
          <Search
            class="search-panel__icon"
            :size="19"
            :stroke-width="1.6"
            aria-hidden="true"
          />
          <label class="visually-hidden" for="section-search"
            >Поисковый запрос</label
          >
          <input
            id="section-search"
            ref="searchInput"
            v-model="searchQuery"
            type="search"
            placeholder="Поиск по названию, коллекции, артикулу..."
            autocomplete="off"
          />
          <UiIconButton
            v-if="searchQuery"
            label="Очистить поиск"
            plain
            @click="clearSearch"
          >
            <X :size="16" :stroke-width="1.2" aria-hidden="true" />
          </UiIconButton>
        </div>
        <nav
          v-if="searchQuery.trim()"
          class="search-panel__results"
          aria-label="Найденные разделы"
        >
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
  </Transition>
</template>

<style scoped>
.menu-panel,
.search-panel {
  outline: none;
}
.topbar {
  border-bottom: 1px solid var(--color-line);
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.08em;
}
.topbar__inner {
  display: flex;
  flex-wrap: wrap;
  min-height: 40px;
  align-items: center;
  justify-content: space-between;
  gap: 4px 20px;
}
.topbar__inner span {
  min-width: 0;
  overflow-wrap: anywhere;
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
  flex: 0 1 auto;
  min-width: 0;
  font-size: 19px;
  font-weight: 600;
  letter-spacing: 0.19em;
  white-space: nowrap;
}
.wordmark img {
  display: block;
  width: min(240px, 50vw);
  max-width: 100%;
  height: 48px;
  object-fit: contain;
  object-position: left center;
}
.wordmark--small {
  font-size: 16px;
}
.wordmark--small img {
  width: min(200px, 50vw);
  height: 40px;
}
.site-nav {
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  justify-content: center;
  min-width: 0;
  max-height: min(144px, 25dvh);
  overflow-y: auto;
  overscroll-behavior: contain;
  gap: 4px clamp(18px, 3vw, 44px);
  padding: 8px 6px;
  scroll-padding-block: 8px;
}
.site-nav a {
  position: relative;
  padding-block: 8px;
  font-size: 13px;
  letter-spacing: 0.04em;
  max-width: 100%;
  white-space: normal;
  overflow-wrap: anywhere;
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
  flex-shrink: 0;
  align-items: center;
  gap: 8px;
}
@media (max-width: 480px) {
  .site-header__inner {
    gap: 4px;
  }
  .site-header__actions {
    gap: 0;
  }
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
.backdrop--search {
  top: var(--search-top);
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
.menu-panel__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}
.menu-panel__top {
  margin-bottom: 56px;
}
.menu-panel__nav {
  display: flex;
  flex-direction: column;
}
.menu-panel__nav a {
  display: flex;
  min-width: 0;
  overflow-wrap: anywhere;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--color-line);
  padding: 14px 0;
  font-size: clamp(26px, 3vw, 34px);
  font-weight: 500;
  letter-spacing: -0.01em;
  animation: menu-link-enter 0.6s var(--ease-out) both;
  animation-delay: calc(0.15s + var(--menu-link-index) * 0.07s);
}
.menu-panel__nav a:hover {
  color: var(--color-muted);
}
.menu-panel__nav span {
  flex-shrink: 0;
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
  animation: menu-footer-enter 0.6s var(--ease-out) 0.45s both;
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
  animation: menu-footer-enter 0.6s var(--ease-out) 0.45s both;
}
@keyframes menu-link-enter {
  from {
    opacity: 0;
    transform: translateX(40px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@keyframes menu-footer-enter {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
.search-panel {
  top: var(--search-top);
  right: 0;
  left: 0;
  max-height: max(0px, calc(100dvh - var(--search-top)));
  overflow-y: auto;
  padding: 12px 0;
  background: var(--color-bg);
  border-bottom: 1px solid var(--color-line);
}
.search-panel__row {
  display: flex;
  min-height: 44px;
  align-items: center;
  gap: 20px;
}
.search-panel__icon {
  flex-shrink: 0;
}
.search-panel input {
  min-width: 0;
  width: 100%;
  border: 0;
  border-radius: 0;
  padding: 8px 0;
  outline: 0;
  background: transparent;
  color: var(--color-ink);
  font-size: 15px;
  font-weight: 400;
  line-height: 1.8;
  letter-spacing: normal;
}
.search-panel input::-webkit-search-cancel-button {
  appearance: none;
}
.search-panel__results {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 32px;
  max-height: 280px;
  overflow-y: auto;
  margin-top: 20px;
}
.search-panel__results a {
  display: flex;
  min-width: 0;
  overflow-wrap: anywhere;
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
  .topbar__text {
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
    padding: 8px 0;
  }
  .search-panel__row {
    gap: 12px;
  }
  .search-panel__results {
    grid-template-columns: 1fr;
  }
}
</style>
