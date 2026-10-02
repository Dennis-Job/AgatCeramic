<script setup lang="ts">
import AdminHeader from './components/AdminHeader.vue'
</script>

<template>
  <div class="min-h-screen bg-page text-gray-800">
    <AdminHeader />
    <main id="admin-main" class="min-w-0 admin-layout-main">
      <div class="admin-layout-content">
        <slot />
      </div>
    </main>
  </div>
</template>

<style scoped>
.admin-layout-content {
  --admin-workspace-inline-gutter: var(--admin-workspace-gutter-compact);
  min-width: 0;
  padding: var(--admin-workspace-inline-gutter);
}
@media (min-width: 640px) {
  .admin-layout-content {
    --admin-workspace-inline-gutter: var(--admin-workspace-gutter);
  }
}
</style>
