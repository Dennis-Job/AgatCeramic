<script setup lang="ts">
import EmployeesWorkspace from '../features/employees/components/EmployeesWorkspace.vue'
import TeamAccessNavigation from '../layouts/components/TeamAccessNavigation.vue'
</script>

<template>
  <EmployeesWorkspace>
    <template #navigation><TeamAccessNavigation /></template>
  </EmployeesWorkspace>
</template>
