<script setup lang="ts">
import type { HomePageContent } from '~/types/homePage'
import SectionHeading from '~/components/shared/SectionHeading.vue'
import HomeCategoryCard from './HomeCategoryCard.vue'

defineProps<{ content: HomePageContent['categories'] }>()
defineEmits<{ select: [id: string] }>()
</script>

<template>
  <section
    id="catalog"
    class="section categories-section"
    aria-labelledby="catalog-title"
  >
    <div class="container">
      <SectionHeading
        title-id="catalog-title"
        :eyebrow="content.eyebrow"
        :title="content.title"
        :description="content.description"
      />
      <div class="categories-section__grid">
        <HomeCategoryCard
          v-for="category in content.items"
          :key="category.id"
          :category="category"
          @select="$emit('select', $event)"
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.categories-section {
  scroll-margin-top: 78px;
}
.categories-section__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
}
@media (max-width: 900px) {
  .categories-section__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }
}
@media (max-width: 640px) {
  .categories-section__grid {
    grid-template-columns: 1fr;
    gap: 48px;
  }
}
</style>
