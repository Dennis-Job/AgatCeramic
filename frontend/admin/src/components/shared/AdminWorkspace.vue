<script setup lang="ts">
defineProps<{ mode: 'overview' | 'list' | 'editor' | 'form' }>()
</script>

<template>
  <section class="admin-workspace" :class="`admin-workspace--${mode}`">
    <div v-if="$slots.intro" class="admin-workspace-intro admin-container">
      <slot name="intro" />
    </div>
    <slot />
  </section>
</template>

<style scoped>
.admin-workspace {
  width: 100%;
  min-width: 0;
  margin-inline: auto;
}
.admin-workspace:not(.admin-workspace--list) {
  max-width: var(--admin-container-max-width);
}
.admin-workspace :deep(.grid),
.admin-workspace :deep(.grid > *) {
  min-width: 0;
}
</style>
