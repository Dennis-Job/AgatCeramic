<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ count: number; max?: number }>(), {
  max: 9999,
})
const fullCount = computed(() => props.count.toLocaleString('ru-RU'))
const label = computed(() =>
  props.count > props.max
    ? `${props.max.toLocaleString('ru-RU')}+`
    : fullCount.value,
)
</script>

<template>
  <span
    v-if="count > 0"
    class="admin-notification-count"
    aria-hidden="true"
    :title="fullCount"
  >
    {{ label }}
  </span>
</template>

<style scoped>
.admin-notification-count {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  min-width: var(--admin-count-height);
  height: var(--admin-count-height);
  padding-inline: var(--admin-spacing-1);
  border-radius: var(--admin-count-radius);
  background: var(--admin-color-count-background);
  color: var(--admin-color-white);
  font-size: var(--admin-count-font-size);
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
