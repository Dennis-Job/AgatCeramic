<template>
  <div class="admin-editor-layout">
    <div
      class="admin-editor-grid"
      :class="{ 'admin-editor-grid--navigation': $slots.navigation }"
    >
      <div v-if="$slots.navigation" class="min-w-0">
        <slot name="navigation" />
      </div>
      <div class="min-w-0"><slot name="editor" /></div>
      <div class="admin-editor-preview min-w-0"><slot name="preview" /></div>
    </div>
  </div>
</template>

<style scoped>
.admin-editor-layout {
  min-width: 0;
  container-type: inline-size;
}
.admin-editor-grid {
  display: grid;
  min-width: 0;
  align-items: start;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--admin-spacing-4);
}
@container (min-width: 960px) {
  .admin-editor-grid--navigation {
    grid-template-columns: var(--admin-editor-navigation-width) minmax(0, 1fr);
  }
  .admin-editor-grid--navigation .admin-editor-preview {
    grid-column: 1 / -1;
  }
}
@container (min-width: 1120px) {
  .admin-editor-grid:not(.admin-editor-grid--navigation) {
    grid-template-columns: minmax(0, 1fr) minmax(
        var(--admin-editor-preview-min-width),
        0.9fr
      );
  }
}
@container (min-width: 1240px) {
  .admin-editor-grid--navigation {
    grid-template-columns:
      var(--admin-editor-navigation-width) minmax(0, 1fr)
      minmax(var(--admin-editor-preview-min-width), 0.9fr);
  }
  .admin-editor-grid--navigation .admin-editor-preview {
    grid-column: auto;
  }
}
</style>
