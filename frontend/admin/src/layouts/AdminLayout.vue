<script setup lang="ts">
import { ref } from 'vue'
import AdminHeader from './components/AdminHeader.vue'
import AdminSidebar from './components/AdminSidebar.vue'

const isSidebarOpen = ref(false)
</script>

<template>
  <div class="min-h-screen bg-page text-gray-800">
    <AdminSidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <main class="min-h-screen admin-layout-main">
      <AdminHeader :is-navigation-open="isSidebarOpen" @open-navigation="isSidebarOpen = true" />
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>
