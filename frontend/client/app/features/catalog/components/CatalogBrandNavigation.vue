<script setup lang="ts">
import type { CatalogBrand } from '../types/catalog'
import { groupBrands } from '../utils/brandAlphabet'
import { useBrandAlphabetMenu } from '../composables/useBrandAlphabetMenu'

const props = defineProps<{ brands: CatalogBrand[]; path: string }>()
const groups = computed(() => groupBrands(props.brands))
const { root, openKey, positions, close, enter, toggle, leave, revealLink } =
  useBrandAlphabetMenu(groups)
const id = useId()
const route = useRoute()
</script>

<template>
  <nav
    v-if="groups.length"
    ref="root"
    class="catalog-brand-navigation"
    aria-label="Бренды по алфавиту"
    @pointerleave="leave"
    @focusin="revealLink"
  >
    <span class="catalog-brand-navigation__label">Бренды:</span>
    <ul class="catalog-brand-navigation__alphabet">
      <li v-for="group in groups" :key="group.key">
        <button
          :id="`${id}-trigger-${group.key}`"
          type="button"
          class="catalog-brand-navigation__trigger ui-hover-underline"
          :data-brand-trigger="group.key"
          :aria-expanded="openKey === group.key"
          :aria-controls="`${id}-panel-${group.key}`"
          @pointerenter="enter(group.key, $event)"
          @click="toggle(group.key, $event)"
        >
          {{ group.label }}
        </button>
        <div
          :id="`${id}-panel-${group.key}`"
          :data-brand-panel="group.key"
          class="catalog-brand-navigation__panel ui-submenu-popover"
          popover="manual"
          :aria-labelledby="`${id}-trigger-${group.key}`"
          :inert="openKey !== group.key"
          :aria-hidden="openKey !== group.key"
          :style="positions[group.key]"
        >
          <ul class="catalog-brand-navigation__brands">
            <li v-for="brand in group.brands" :key="brand.id">
              <NuxtLink
                :to="{
                  path,
                  query: { brand: brand.slug },
                  hash: '#catalog-products',
                }"
                :aria-current="
                  route.query.brand === brand.slug ? 'true' : 'false'
                "
                @click="close()"
                >{{ brand.name.trim() }}</NuxtLink
              >
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
.catalog-brand-navigation {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 12px 24px;
  margin-bottom: 32px;
  border-block: 1px solid var(--color-line);
  color: var(--color-muted);
  font-size: 13px;
}
.catalog-brand-navigation__label {
  padding-block: 14px;
}
.catalog-brand-navigation__alphabet {
  display: flex;
  flex: 0 1 auto;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0 12px;
  min-width: 0;
}
ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
li {
  min-width: 0;
}
.catalog-brand-navigation__trigger {
  min-width: 44px;
  min-height: 44px;
  border: 0;
  padding: 14px 8px;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}
.catalog-brand-navigation__trigger:hover,
.catalog-brand-navigation__trigger[aria-expanded='true'] {
  color: var(--color-ink);
}
.catalog-brand-navigation__panel {
  position: fixed;
  inset: auto;
  box-sizing: border-box;
  margin: 0;
  border: 1px solid var(--color-line);
  padding: 24px;
  overflow-y: auto;
  overscroll-behavior: contain;
  background: var(--color-bg-soft);
  color: var(--color-ink);
  box-shadow: 0 12px 28px var(--color-shadow-soft);
}
.catalog-brand-navigation__brands {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 4px 24px;
}
.catalog-brand-navigation__brands a {
  display: block;
  min-height: 44px;
  padding: 12px;
  font-size: 13px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.catalog-brand-navigation__brands a:hover {
  background: var(--color-beige);
}
.catalog-brand-navigation__brands a:focus-visible {
  outline-offset: -3px;
}
@media (max-width: 1023px) {
  .catalog-brand-navigation__brands {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 639px) {
  .catalog-brand-navigation {
    flex-wrap: wrap;
    gap: 0 12px;
  }
  .catalog-brand-navigation__panel {
    padding: 12px;
  }
  .catalog-brand-navigation__brands {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
