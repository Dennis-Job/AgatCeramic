<script setup lang="ts">
withDefaults(defineProps<{ padded?: boolean; surface?: 'white' | 'muted' }>(), {
  padded: true,
  surface: 'muted',
})
</script>
<template>
  <section
    class="admin-panel"
    :class="[
      surface === 'white' && 'admin-panel--white',
      {
        'p-5': padded && !$slots.header,
        'flex flex-col overflow-hidden': $slots.header,
      },
    ]"
  >
    <template v-if="$slots.header">
      <header class="bg-gray-50 px-5 py-4">
        <slot name="header" />
      </header>
      <div
        class="admin-panel--inset mx-1.5 mb-1.5 flex-1"
        :class="padded && 'p-4 sm:p-5'"
      >
        <slot />
      </div>
    </template>
    <slot v-else />
  </section>
</template>
