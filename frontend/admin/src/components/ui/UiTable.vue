<script setup lang="ts">
withDefaults(
  defineProps<{
    minWidth?: string
    label?: string
    tableClass?: string
    stickyHeader?: boolean
    stickyEdges?: boolean
  }>(),
  {
    minWidth: undefined,
    label: undefined,
    tableClass: undefined,
    stickyHeader: false,
    stickyEdges: false,
  },
)
</script>

<template>
  <div
    class="min-w-0 max-w-full overflow-x-auto [contain:paint] admin-focus-inset"
    :class="{
      'ui-table-sticky-header': stickyHeader,
      'ui-table-sticky-edges': stickyEdges,
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
  color: var(--admin-color-gray-700);
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
.ui-table-sticky-header table {
  border-collapse: separate;
  border-spacing: 0;
}
.ui-table-sticky-header :deep(thead th) {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--admin-color-gray-50);
  box-shadow: inset 0 -1px var(--admin-color-gray-200);
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
