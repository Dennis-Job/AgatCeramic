<template>
  <div class="catalog-reference-layout admin-container">
    <aside class="catalog-reference-sidebar min-w-0">
      <slot name="sidebar" />
    </aside>
    <div class="catalog-reference-mobile min-w-0"><slot name="mobile" /></div>
    <div class="catalog-reference-content min-w-0"><slot /></div>
  </div>
</template>
<style scoped>
.catalog-reference-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-width: 0;
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
}
.catalog-reference-sidebar {
  display: none;
}
.catalog-reference-mobile,
.catalog-reference-content {
  padding-block: var(--admin-spacing-5);
}
@media (min-width: 1024px) {
  .catalog-reference-layout {
    grid-template-columns: 240px minmax(0, 1fr);
  }
  .catalog-reference-sidebar {
    display: block;
    padding-block: var(--admin-spacing-5);
    padding-right: var(--admin-spacing-5);
    border-right: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
  .catalog-reference-mobile {
    display: none;
  }
  .catalog-reference-content {
    padding-left: var(--admin-spacing-6);
  }
}
</style>
