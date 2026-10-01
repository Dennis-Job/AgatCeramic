<script setup lang="ts">
withDefaults(defineProps<{ padded?: boolean; surface?: 'white' | 'muted' }>(), {
  padded: true,
  surface: 'white',
})
</script>
<template>
  <section
    class="rounded-2xl border shadow-card"
    :class="[
      surface === 'muted'
        ? 'border-transparent bg-gray-50'
        : 'border-gray-200 bg-white',
      { 'p-5': padded },
    ]"
  >
    <slot />
  </section>
</template>
