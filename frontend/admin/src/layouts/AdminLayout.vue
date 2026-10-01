<script setup lang="ts">
import AdminHeader from './components/AdminHeader.vue'
</script>

<template>
  <div class="min-h-screen bg-page text-gray-800">
    <AdminHeader />
    <main id="admin-main" class="min-w-0 admin-layout-main">
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
