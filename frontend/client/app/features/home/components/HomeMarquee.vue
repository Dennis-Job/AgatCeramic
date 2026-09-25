<script setup lang="ts">
const topics = [
  'Керамогранит',
  'Плитка',
  'Мозаика',
  'Фактура',
  'Формат',
  'Пространство',
]
</script>

<template>
  <div
    class="marquee"
    aria-label="Керамогранит, плитка, мозаика, фактура, формат, пространство"
  >
    <div class="marquee__track" aria-hidden="true">
      <div v-for="copy in 2" :key="copy" class="marquee__set">
        <span v-for="topic in topics" :key="topic"
          ><strong>{{ topic }}</strong> ·</span
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.marquee {
  overflow: hidden;
  border-block: 1px solid var(--color-line);
  padding: 20px 0;
  background: var(--color-bg-soft);
}
.marquee__track {
  display: flex;
  width: max-content;
  animation: move 30s linear infinite;
}
.marquee__set {
  display: flex;
  flex: 0 0 auto;
}
.marquee__set span {
  padding-inline: 26px;
  color: var(--color-muted);
  font-size: 12px;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  white-space: nowrap;
}
.marquee__set strong {
  color: var(--color-ink);
  font-weight: 500;
}
@keyframes move {
  to {
    transform: translateX(-50%);
  }
}
@media (prefers-reduced-motion: reduce) {
  .marquee__track {
    animation: none;
  }
}
</style>
