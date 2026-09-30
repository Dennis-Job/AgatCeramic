<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'

defineProps<{
  title: string
  message: string
  error?: boolean
  loading?: boolean
}>()
defineEmits<{ retry: [] }>()
</script>

<template>
  <div
    class="page-state"
    :role="error ? 'alert' : 'status'"
    :aria-busy="loading"
  >
    <h2>{{ title }}</h2>
    <p>{{ message }}</p>
    <UiButton v-if="error" :disabled="loading" @click="$emit('retry')"
      >Повторить загрузку</UiButton
    >
  </div>
</template>

<style scoped>
.page-state {
  padding-block: 48px;
  overflow-wrap: anywhere;
}
h2 {
  margin: 0 0 16px;
  font-size: clamp(22px, 3vw, 30px);
  font-weight: 500;
}
p {
  margin: 0 0 24px;
  color: var(--color-muted);
  line-height: 1.7;
}
</style>
