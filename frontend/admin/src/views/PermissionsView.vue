<script setup lang="ts">
import PermissionsWorkspace from '../features/access-control/components/PermissionsWorkspace.vue'
import TeamAccessNavigation from '../layouts/components/TeamAccessNavigation.vue'
</script>

<template>
  <PermissionsWorkspace>
    <template #navigation><TeamAccessNavigation /></template>
  </PermissionsWorkspace>
</template>
