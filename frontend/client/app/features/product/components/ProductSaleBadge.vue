<template>
  <span class="product-sale-badge">Распродажа</span>
</template>

<style scoped>
.product-sale-badge {
  position: absolute;
  z-index: 2;
  top: var(--product-badge-inset, 16px);
  left: var(--product-badge-inset, 16px);
  max-width: calc(100% - 2 * var(--product-badge-inset, 16px));
  padding: 7px 12px;
  background: var(--color-ink);
  color: var(--color-white);
  font-size: 9px;
  font-weight: 500;
  line-height: 1.2;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  overflow-wrap: anywhere;
  pointer-events: none;
}
</style>
