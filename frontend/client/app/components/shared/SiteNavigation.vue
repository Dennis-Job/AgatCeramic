<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import type { SiteNavigationItem } from '~/types/homePage'

const props = defineProps<{
  items: SiteNavigationItem[]
  variant: 'desktop' | 'drawer'
  label: string
  currentPath?: string
  headerBottom?: number
}>()
const emit = defineEmits<{ navigate: [] }>()
const route = useRoute()
const router = useRouter()
const root = ref<HTMLElement | null>(null)
const openIndex = ref<number | null>(null)
const id = useId()
const positions = ref<Record<number, Record<string, string>>>({})

function isCurrent(to: string): boolean {
  if (!to.startsWith('/')) return false
  const target = router.resolve(to)
  return (
    target.path === (props.currentPath ?? route.path) &&
    Object.entries(target.query).every(
      ([key, value]) =>
        JSON.stringify(route.query[key]) === JSON.stringify(value),
    ) &&
    (!target.hash ||
      target.hash === route.hash ||
      (!route.hash && target.hash === '#home'))
  )
}

function isCurrentGroup(item: SiteNavigationItem): boolean {
  return 'children' in item && item.children.some((link) => isCurrent(link.to))
}

function groupId(index: number): string {
  return `${id}-group-${index}`
}

function trigger(index: number) {
  return root.value?.querySelector<HTMLButtonElement>(
    `[data-trigger="${index}"]`,
  )
}

function submenu(index: number) {
  return root.value?.querySelector<HTMLElement>(`[data-group="${index}"]`)
}

function close(restoreFocus = false): boolean {
  const index = openIndex.value
  if (index === null) return false
  const element = submenu(index)
  if (element?.matches(':popover-open')) element.hidePopover()
  openIndex.value = null
  if (restoreFocus) trigger(index)?.focus({ preventScroll: true })
  return true
}

function updatePosition() {
  if (props.variant !== 'desktop' || openIndex.value === null) return
  const button = trigger(openIndex.value)
  const element = submenu(openIndex.value)
  if (!button || !element || !root.value) return
  const anchor = button.getBoundingClientRect()
  const navigation = root.value.getBoundingClientRect()
  if (
    !root.value.clientWidth ||
    anchor.bottom <= navigation.top ||
    anchor.top >= navigation.bottom
  ) {
    close()
    return
  }
  const margin = 12
  const width = Math.min(300, document.documentElement.clientWidth - margin * 2)
  const top = props.headerBottom ?? navigation.bottom
  const available = Math.max(0, window.innerHeight - top - margin)
  positions.value[openIndex.value] = {
    left: `${Math.max(margin, Math.min(anchor.left, document.documentElement.clientWidth - width - margin))}px`,
    top: `${top}px`,
    width: `${width}px`,
    maxHeight: `${available}px`,
  }
}

async function toggle(index: number) {
  if (openIndex.value === index) {
    close()
    return
  }
  close()
  openIndex.value = index
  await nextTick()
  if (openIndex.value !== index || props.variant !== 'desktop') return
  submenu(index)?.showPopover()
  updatePosition()
}

function navigate() {
  close()
  emit('navigate')
}

function outside(event: Event) {
  if (event.target instanceof Node && !root.value?.contains(event.target))
    close()
}

watch(
  () => route.fullPath,
  () => close(),
)
watch(
  () => props.items,
  () => close(),
)
watch(() => props.headerBottom, updatePosition)

onMounted(() => {
  document.addEventListener('pointerdown', outside)
  document.addEventListener('focusin', outside)
  window.addEventListener('resize', updatePosition)
  window.addEventListener('scroll', updatePosition, true)
})
onBeforeUnmount(() => {
  close()
  document.removeEventListener('pointerdown', outside)
  document.removeEventListener('focusin', outside)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', updatePosition, true)
})

defineExpose({ close })
</script>

<template>
  <nav
    ref="root"
    :class="variant === 'desktop' ? 'site-nav' : 'menu-panel__nav'"
    :aria-label="label"
  >
    <ul class="navigation-list">
      <li
        v-for="(item, index) in items"
        :key="index"
        class="navigation-item"
        :style="{ '--menu-link-index': index }"
      >
        <template v-if="'children' in item">
          <button
            class="navigation-link navigation-trigger"
            :class="{ 'is-current': isCurrentGroup(item) }"
            type="button"
            :data-trigger="index"
            :aria-expanded="openIndex === index"
            :aria-controls="groupId(index)"
            @click="toggle(index)"
          >
            <span>{{ item.label }}</span>
            <ChevronDown
              v-if="variant === 'drawer'"
              :size="16"
              :stroke-width="1.6"
              aria-hidden="true"
            />
          </button>
          <div
            :id="groupId(index)"
            :data-group="index"
            class="navigation-submenu"
            :class="{
              'ui-submenu-popover': variant === 'desktop',
              'ui-submenu-drawer': variant === 'drawer',
              'is-open': openIndex === index,
            }"
            :popover="variant === 'desktop' ? 'manual' : undefined"
            :inert="openIndex !== index"
            :aria-hidden="openIndex !== index"
            :style="variant === 'desktop' ? positions[index] : undefined"
          >
            <div class="navigation-submenu__content">
              <ul class="navigation-submenu__list">
                <li
                  v-for="(link, childIndex) in item.children"
                  :key="childIndex"
                >
                  <NuxtLink
                    :to="link.to"
                    :aria-current="isCurrent(link.to) ? 'page' : undefined"
                    @click="navigate"
                    >{{ link.label }}</NuxtLink
                  >
                </li>
              </ul>
            </div>
          </div>
        </template>
        <NuxtLink
          v-else
          class="navigation-link"
          :to="item.to"
          :aria-current="isCurrent(item.to) ? 'page' : undefined"
          @click="navigate"
        >
          <span>{{ item.label }}</span>
          <span
            v-if="variant === 'drawer'"
            class="navigation-number"
            aria-hidden="true"
            >{{ String(index + 1).padStart(2, '0') }}</span
          >
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
.navigation-list,
.navigation-submenu__list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.navigation-item {
  min-width: 0;
  max-width: 100%;
}
.navigation-link {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  max-width: 100%;
  border: 0;
  padding: 8px 0;
  background: transparent;
  color: inherit;
  text-align: left;
  font: inherit;
  overflow-wrap: anywhere;
}
.navigation-link > span:first-child {
  min-width: 0;
}
.navigation-trigger svg {
  flex-shrink: 0;
  transition: transform 0.3s var(--ease-out);
}
.navigation-trigger[aria-expanded='true'] svg {
  transform: rotate(180deg);
}
.site-nav {
  flex: 1;
  min-width: 0;
  max-height: min(144px, 25dvh);
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 8px 6px;
  scroll-padding-block: 8px;
}
.site-nav > .navigation-list {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 4px clamp(18px, 3vw, 44px);
}
.site-nav .navigation-link {
  font-size: 13px;
  letter-spacing: 0.04em;
}
.site-nav .navigation-link::after {
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
.site-nav .navigation-link:hover::after,
.site-nav .navigation-link[aria-current]::after,
.site-nav .navigation-trigger.is-current::after {
  transform: scaleX(1);
  transform-origin: left;
}
.navigation-submenu {
  color: var(--color-ink);
  background: var(--color-bg-soft);
}
.navigation-submenu[popover]:not(:popover-open) {
  display: none;
}
.navigation-submenu[popover] {
  position: fixed;
  inset: auto;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid var(--color-line);
  padding: 8px;
  box-shadow: 0 12px 28px var(--color-shadow-soft);
}
.navigation-submenu a {
  display: block;
  min-width: 0;
  padding: 12px;
  font-size: 14px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.navigation-submenu a:hover,
.navigation-submenu a[aria-current] {
  background: var(--color-beige);
}
.navigation-submenu a[aria-current] {
  text-decoration: underline;
  text-underline-offset: 4px;
}
.navigation-submenu a:focus-visible {
  outline-offset: -3px;
}
.menu-panel__nav > .navigation-list {
  display: flex;
  flex-direction: column;
}
.menu-panel__nav .navigation-item {
  border-bottom: 1px solid var(--color-line);
  animation: menu-link-enter 0.6s var(--ease-out) both;
  animation-delay: calc(0.15s + var(--menu-link-index) * 0.07s);
}
.menu-panel__nav .navigation-link {
  width: 100%;
  padding: 14px 0;
  font-size: clamp(26px, 3vw, 34px);
  font-weight: 500;
  letter-spacing: -0.01em;
}
.menu-panel__nav .navigation-link:hover {
  color: var(--color-muted);
}
.navigation-number {
  flex-shrink: 0;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.2em;
}
.menu-panel__nav .navigation-submenu {
  background: transparent;
}
.menu-panel__nav .navigation-submenu__content {
  min-height: 0;
  overflow: hidden;
}
.menu-panel__nav .navigation-submenu__list {
  padding: 0 0 12px 12px;
}
.menu-panel__nav .navigation-submenu a {
  padding: 12px;
  font-size: 16px;
}
.menu-panel__nav .navigation-trigger.is-current > span {
  text-decoration: underline;
  text-underline-offset: 6px;
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
@media (max-width: 1024px) {
  .site-nav {
    display: none;
  }
}
</style>
