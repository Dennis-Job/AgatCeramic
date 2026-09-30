<script setup lang="ts">
import type { ContentBlock } from '~/types/contentPage'
import HomeHero from '~/features/home/components/HomeHero.vue'
import HomeMarquee from '~/features/home/components/HomeMarquee.vue'
import HomeCategories from '~/features/home/components/HomeCategories.vue'
import HomeMaterialShowcase from '~/features/home/components/HomeMaterialShowcase.vue'
import HomePromo from '~/features/home/components/HomePromo.vue'
import HomeAbout from '~/features/home/components/HomeAbout.vue'
import HomeGuide from '~/features/home/components/HomeGuide.vue'
import StoreContacts from './StoreContacts.vue'
import CatalogListing from './CatalogListing.vue'

const props = defineProps<{
  blocks: ContentBlock[]
  title: string
  description?: string
}>()
const blocks = computed(() => props.blocks.filter((block) => block.enabled))
const categories = computed(
  () =>
    blocks.value.find((block) => block.type === 'categories')?.data.items ?? [],
)
const selectedMaterialId = ref('')
const hasMaterials = computed(() =>
  blocks.value.some((block) => block.type === 'materials'),
)
watch(
  categories,
  (items) => {
    if (!items.some((item) => item.id === selectedMaterialId.value))
      selectedMaterialId.value = items[0]?.id ?? ''
  },
  { immediate: true },
)

async function showMaterial(id: string) {
  selectedMaterialId.value = id
  await nextTick()
  document.getElementById(`tab-${id}`)?.focus({ preventScroll: true })
  document.getElementById('materials')?.scrollIntoView({
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
      ? 'auto'
      : 'smooth',
  })
}
</script>

<template>
  <template v-for="block in blocks" :key="block.id">
    <HomeHero
      v-if="block.type === 'hero'"
      :slides="block.data.slides"
      :empty-title="title"
      :empty-description="description || ''"
    />
    <HomeMarquee
      v-else-if="block.type === 'marquee' && block.data.topics.length"
      :topics="block.data.topics"
    />
    <HomeCategories
      v-else-if="block.type === 'categories' && block.data.items.length"
      :content="block.data"
      :interactive="hasMaterials"
      @select="showMaterial"
    />
    <HomeMaterialShowcase
      v-else-if="block.type === 'materials' && categories.length"
      :categories="categories"
      :content="block.data"
      :selected-id="selectedMaterialId"
      @select="selectedMaterialId = $event"
    />
    <HomePromo v-else-if="block.type === 'promo'" :content="block.data" />
    <HomeAbout v-else-if="block.type === 'about'" :content="block.data" />
    <HomeGuide
      v-else-if="block.type === 'guide' && block.data.items.length"
      :content="block.data"
    />
    <section v-else-if="block.type === 'text'" class="section">
      <div class="container text-block">
        <h2 v-if="block.data.title">{{ block.data.title }}</h2>
        <p v-if="block.data.body">{{ block.data.body }}</p>
      </div>
    </section>
    <StoreContacts
      v-else-if="block.type === 'stores'"
      :title="block.data.title"
    />
    <CatalogListing
      v-else-if="block.type === 'catalog'"
      :title="block.data.title"
      :description="block.data.description"
    />
  </template>
</template>

<style scoped>
.text-block {
  max-width: 880px;
  overflow-wrap: anywhere;
}
h2 {
  margin: 0 0 28px;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.2;
}
p {
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.9;
  white-space: pre-line;
}
</style>
