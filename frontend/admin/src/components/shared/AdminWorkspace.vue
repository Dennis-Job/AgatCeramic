<script setup lang="ts">
defineProps<{ mode: 'overview' | 'list' | 'editor' | 'form' }>()
</script>

<template>
  <section class="admin-workspace" :class="`admin-workspace--${mode}`">
    <div v-if="$slots.intro" class="admin-workspace-intro">
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
.admin-workspace--overview,
.admin-workspace-intro {
  max-width: var(--admin-workspace-overview-max-width);
  margin-inline: auto;
}
.admin-workspace--form {
  max-width: var(--admin-workspace-form-max-width);
}
.admin-workspace :deep(.grid),
.admin-workspace :deep(.grid > *) {
  min-width: 0;
}
</style>
