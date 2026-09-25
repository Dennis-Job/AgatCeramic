<script setup lang="ts">
import type { MaterialCategory } from '../data/homeContent'

defineProps<{ category: MaterialCategory }>()
defineEmits<{ select: [id: string] }>()
</script>

<template>
  <button
    class="category-card"
    type="button"
    :aria-label="`Подробнее: ${category.name}`"
    @click="$emit('select', category.id)"
  >
    <span class="category-card__image">
      <img
        :src="category.image"
        :alt="category.imageAlt"
        width="880"
        height="1100"
        loading="lazy"
      />
      <span class="category-card__image-label" aria-hidden="true"
        >Смотреть</span
      >
    </span>
    <span class="category-card__name">{{ category.name }}</span>
    <span class="category-card__description">{{
      category.shortDescription
    }}</span>
    <span class="category-card__link" aria-hidden="true"
      >Подробнее <span>→</span></span
    >
  </button>
</template>

<style scoped>
.category-card {
  display: block;
  width: 100%;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--color-ink);
  text-align: left;
}
.category-card__image {
  position: relative;
  display: block;
  overflow: hidden;
  aspect-ratio: 4 / 4.4;
  background: var(--color-beige);
}
.category-card__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 1.1s var(--ease-out);
}
.category-card:hover .category-card__image img {
  transform: scale(1.07);
}
.category-card__image-label {
  position: absolute;
  bottom: 24px;
  left: 24px;
  background: var(--color-card-label-surface);
  padding: 12px 20px;
  font-size: 10px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  opacity: 0;
  transform: translateY(12px);
  transition:
    opacity 0.35s,
    transform 0.35s var(--ease-out);
}
.category-card:hover .category-card__image-label,
.category-card:focus-visible .category-card__image-label {
  opacity: 1;
  transform: none;
}
.category-card__name {
  display: block;
  margin-top: 20px;
  font-size: 18px;
  font-weight: 500;
}
.category-card__description {
  display: block;
  margin-top: 6px;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.6;
}
.category-card__link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 15px;
  color: var(--color-muted);
  font-size: 12px;
  letter-spacing: 0.1em;
  transition: color 0.25s;
}
.category-card:hover .category-card__link {
  color: var(--color-ink);
}
</style>
