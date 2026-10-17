<template>
  <div class="admin-editor-layout">
    <header
      v-if="$slots.toolbar || $slots['toolbar-navigation']"
      class="admin-editor-toolbar admin-container"
    >
      <div
        v-if="$slots['toolbar-navigation']"
        class="admin-editor-toolbar-navigation"
      >
        <slot name="toolbar-navigation" />
      </div>
      <div v-if="$slots.toolbar" class="admin-editor-toolbar-content">
        <slot name="toolbar" />
      </div>
    </header>
    <div
      class="admin-editor-grid"
      :class="{ 'admin-editor-grid--navigation': $slots.navigation }"
    >
      <div v-if="$slots.navigation" class="admin-editor-navigation min-w-0">
        <slot name="navigation" />
      </div>
      <div class="admin-editor-inspector min-w-0"><slot name="editor" /></div>
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
.admin-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-3);
  padding-block: var(--admin-spacing-4);
  margin-bottom: var(--admin-spacing-4);
  border-bottom: var(--admin-border-width) solid var(--admin-color-gray-200);
}
.admin-editor-toolbar-navigation {
  min-width: 0;
}
.admin-editor-toolbar-content {
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-3);
}
@container (min-width: 1120px) {
  .admin-editor-toolbar,
  .admin-editor-toolbar-content {
    flex-wrap: nowrap;
  }
  .admin-editor-toolbar-navigation,
  .admin-editor-toolbar-content > :deep(:last-child) {
    flex-shrink: 0;
  }
  .admin-editor-toolbar-navigation + .admin-editor-toolbar-content {
    min-height: var(--admin-control-height-lg);
    margin-inline-start: var(--admin-spacing-3);
    padding-inline-start: var(--admin-spacing-6);
    border-inline-start: var(--admin-border-width) solid
      var(--admin-color-gray-200);
  }
}
.admin-editor-navigation,
.admin-editor-inspector,
.admin-editor-preview {
  padding: var(--admin-spacing-3);
}
.admin-editor-inspector {
  border-block: var(--admin-border-width) solid var(--admin-color-gray-200);
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
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
}
@container (min-width: 1360px) {
  .admin-editor-grid--navigation {
    grid-template-columns:
      var(--admin-editor-navigation-width)
      minmax(
        0,
        calc(
          50% - var(--admin-editor-navigation-width) - var(--admin-spacing-4)
        )
      )
      minmax(0, calc(50% - var(--admin-spacing-4)));
  }
  .admin-editor-grid--navigation .admin-editor-preview {
    grid-column: auto;
  }
  .admin-editor-inspector {
    border-block: 0;
    border-inline: var(--admin-border-width) solid var(--admin-color-gray-200);
    min-height: 720px;
  }
  .admin-editor-preview {
    position: sticky;
    top: calc(var(--admin-shell-height) + var(--admin-spacing-4));
  }
}
</style>
