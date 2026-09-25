<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
  description?: string
  titleId?: string
}>()
</script>

<template>
  <div class="section-heading">
    <span class="eyebrow">{{ eyebrow }}</span>
    <h2 :id="titleId">{{ title }}</h2>
    <p v-if="description">{{ description }}</p>
  </div>
</template>

<style scoped>
.section-heading {
  margin-bottom: 64px;
  text-align: center;
}

h2 {
  margin: 14px 0 12px;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.18;
}

p {
  margin: 0;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.7;
}

@media (max-width: 760px) {
  .section-heading {
    margin-bottom: 42px;
  }
}
</style>
