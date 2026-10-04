<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    minWidth?: string
    label?: string
    tableClass?: string
    stickyHeader?: boolean | 'page'
    stickyEdges?: boolean
    fullBleed?: boolean
  }>(),
  {
    minWidth: undefined,
    label: undefined,
    tableClass: undefined,
    stickyHeader: false,
    stickyEdges: false,
    fullBleed: false,
  },
)

const region = ref<HTMLElement | null>(null)
let stopPageHeader: (() => void) | undefined

// An overflow-x container also captures CSS vertical sticky positioning.
// Move the original header inside that container so sorting and table semantics
// stay intact while the document owns vertical scrolling.
watch(
  () => [region.value, props.stickyHeader] as const,
  ([element, mode]) => {
    stopPageHeader?.()
    stopPageHeader = undefined
    if (!element || mode !== 'page') return

    const table = element.querySelector('table')!
    let frame = 0
    let offset = 0
    const update = () => {
      frame = 0
      const header = table.tHead
      if (!header) return
      const rect = header.getBoundingClientRect()
      const naturalTop = rect.top - offset
      const top = parseFloat(getComputedStyle(header).scrollMarginTop) || 0
      const limit = Math.max(
        0,
        table.getBoundingClientRect().bottom - naturalTop - rect.height,
      )
      offset = Math.min(Math.max(0, top - naturalTop), limit)
      element.style.setProperty('--ui-table-header-offset', `${offset}px`)
    }
    const schedule = () => {
      if (!frame) frame = requestAnimationFrame(update)
    }
    const observer = new ResizeObserver(schedule)
    observer.observe(table)
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', schedule)
    update()
    stopPageHeader = () => {
      observer.disconnect()
      window.removeEventListener('scroll', schedule)
      window.removeEventListener('resize', schedule)
      cancelAnimationFrame(frame)
      element.style.removeProperty('--ui-table-header-offset')
    }
  },
  { flush: 'post' },
)
onBeforeUnmount(() => stopPageHeader?.())
</script>

<template>
  <div
    ref="region"
    class="min-w-0 max-w-full overflow-x-auto [contain:paint] admin-focus-inset"
    :class="{
      'ui-table-sticky-header': stickyHeader === true,
      'ui-table-page-header': stickyHeader === 'page',
      'ui-table-sticky-edges': stickyEdges,
      'ui-table-full-bleed': fullBleed,
    }"
    role="region"
    :aria-label="label"
    :tabindex="label ? 0 : undefined"
  >
    <table
      class="w-full text-left text-sm"
      :class="[minWidth, tableClass]"
      :aria-label="label"
    >
      <slot />
    </table>
  </div>
</template>

<style scoped>
/* Opt in only for page-level list tables within the padded Admin layout. */
.ui-table-full-bleed {
  width: calc(100% + 2 * var(--admin-workspace-inline-gutter));
  max-width: none;
  margin-inline: calc(-1 * var(--admin-workspace-inline-gutter));
}
/* Shared Seller list pattern. Features own columns and domain content. */
.seller-table {
  table-layout: fixed;
}
.seller-table :deep(th),
.seller-table :deep(td) {
  padding: var(--admin-spacing-4);
  vertical-align: top;
  overflow-wrap: anywhere;
}
.seller-table :deep(th) {
  background: var(--admin-color-gray-50);
  color: var(--admin-color-gray-500);
  font-weight: 600;
}
.seller-table :deep(td) {
  border-bottom: 1px solid var(--admin-color-gray-100);
  color: var(--admin-color-gray-500);
}
.seller-table :deep(tbody tr:hover) {
  background: var(--admin-color-gray-50);
}
.ui-table-sticky-header {
  max-height: calc(
    100dvh - var(--admin-shell-height) - var(--admin-spacing-6) * 2
  );
  overflow: auto;
}
.ui-table-sticky-header table,
.ui-table-page-header table {
  border-collapse: separate;
  border-spacing: 0;
}
.ui-table-page-header :deep(thead) {
  position: relative;
  z-index: 2;
  scroll-margin-top: var(--admin-shell-height, 0px);
  transform: translateY(var(--ui-table-header-offset, 0px));
}
.ui-table-sticky-header :deep(thead th),
.ui-table-page-header :deep(thead th) {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--admin-color-gray-50);
  box-shadow: inset 0 -1px var(--admin-color-gray-200);
}
.ui-table-page-header :deep(thead th) {
  position: static;
}
@media (min-width: 1280px) {
  .ui-table-sticky-edges :deep(tr > :first-child),
  .ui-table-sticky-edges :deep(tr > :last-child) {
    position: sticky;
    z-index: 1;
    background: var(--admin-color-white);
  }
  .ui-table-sticky-edges :deep(tr > :first-child) {
    left: 0;
    box-shadow: inset -1px 0 var(--admin-color-gray-200);
  }
  .ui-table-sticky-edges :deep(tr > :last-child) {
    right: 0;
    box-shadow: inset 1px 0 var(--admin-color-gray-200);
  }
  .ui-table-sticky-edges :deep(thead tr > :first-child),
  .ui-table-sticky-edges :deep(thead tr > :last-child) {
    z-index: 3;
    background: var(--admin-color-gray-50);
  }
  .ui-table-sticky-edges :deep(tbody tr:hover > :first-child),
  .ui-table-sticky-edges :deep(tbody tr:hover > :last-child) {
    background: var(--admin-color-gray-50);
  }
}
</style>
