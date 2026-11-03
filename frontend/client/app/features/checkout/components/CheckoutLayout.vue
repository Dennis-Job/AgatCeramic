<script setup lang="ts">
defineProps<{ summaryFirst?: boolean }>()
</script>

<template>
  <div class="checkout-layout">
    <div v-if="summaryFirst" class="checkout-layout__summary">
      <slot name="summary" />
    </div>
    <div class="checkout-layout__main"><slot /></div>
    <div v-if="!summaryFirst" class="checkout-layout__summary">
      <slot name="summary" />
    </div>
  </div>
</template>

<style scoped>
.checkout-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
  grid-template-areas: 'main summary';
  gap: clamp(40px, 5vw, 80px);
  align-items: start;
  padding-bottom: var(--section-space);
}
.checkout-layout__main {
  grid-area: main;
  min-width: 0;
}
.checkout-layout__summary {
  grid-area: summary;
  position: sticky;
  top: 132px;
  min-width: 0;
}
@media (max-width: 1100px) {
  .checkout-layout {
    display: flex;
    flex-direction: column;
  }
  .checkout-layout > div {
    width: 100%;
  }
  .checkout-layout__summary {
    position: static;
  }
}
</style>
