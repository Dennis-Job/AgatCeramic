<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    value?: number
    showScale?: boolean
  }>(),
  { value: undefined, showScale: false },
)
</script>

<template>
  <div>
    <progress
      class="import-progress"
      :aria-label="label"
      :value="value"
      max="100"
    />
    <div
      v-if="showScale"
      class="mt-2 flex justify-between text-xs tabular-nums text-gray-500"
      aria-hidden="true"
    >
      <span>0%</span><span>100%</span>
    </div>
  </div>
</template>

<style scoped>
.import-progress {
  appearance: none;
  display: block;
  width: 100%;
  height: var(--admin-spacing-2);
  overflow: hidden;
  border: 0;
  border-radius: var(--admin-radius-lg);
  background: var(--color-gray-200);
}
.import-progress::-webkit-progress-bar {
  border-radius: inherit;
  background: transparent;
}
.import-progress::-webkit-progress-value {
  border-radius: inherit;
  background: var(--color-primary-500);
}
.import-progress::-moz-progress-bar {
  border-radius: inherit;
  background: var(--color-primary-500);
}
.import-progress:indeterminate {
  background-image: linear-gradient(
    var(--color-primary-500),
    var(--color-primary-500)
  );
  background-repeat: no-repeat;
  background-size: calc(100% / 3) 100%;
  animation: import-progress-active calc(var(--admin-transition-duration) * 8)
    linear infinite;
}
.import-progress:indeterminate::-moz-progress-bar {
  background: transparent;
}
@keyframes import-progress-active {
  from {
    background-position: -50% 0;
  }
  to {
    background-position: 150% 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .import-progress:indeterminate {
    animation: none;
    background-position: 50% 0;
  }
}
</style>
