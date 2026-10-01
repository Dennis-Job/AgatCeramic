<template>
  <div class="admin-list-detail">
    <div class="min-w-0"><slot name="list" /></div>
    <div class="min-w-0"><slot name="detail" /></div>
  </div>
</template>

<style scoped>
.admin-list-detail {
  display: grid;
  min-width: 0;
  align-items: start;
  gap: var(--admin-spacing-5);
}
@media (min-width: 1280px) {
  .admin-list-detail {
    grid-template-columns: minmax(0, 1fr) clamp(380px, 35vw, 720px);
  }
}
</style>
