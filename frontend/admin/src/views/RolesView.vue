<script setup lang="ts">
import RolesWorkspace from '../features/access-control/components/RolesWorkspace.vue'
import TeamAccessNavigation from '../layouts/components/TeamAccessNavigation.vue'
</script>

<template>
  <RolesWorkspace>
    <template #navigation><TeamAccessNavigation /></template>
  </RolesWorkspace>
</template>
