<script setup lang="ts">
import { ref } from 'vue'
import AdminHeader from './components/AdminHeader.vue'

const condensed = ref(false)
</script>

<template>
  <div
    class="min-h-screen bg-page text-gray-800 admin-layout"
    :style="{
      '--admin-shell-height': condensed
        ? 'calc(var(--admin-shell-top-height) + var(--admin-border-width))'
        : undefined,
    }"
  >
    <AdminHeader @condensed-change="condensed = $event" />
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
