<script setup lang="ts">
import { NuxtLink } from '#components'
import type { HomePageContent } from '~/types/homePage'
import SectionHeading from '~/components/shared/SectionHeading.vue'

const props = defineProps<{
  content: HomePageContent['materials']
}>()

const selectedId = ref('')
watch(
  () => props.content.items,
  (items) => {
    if (!items.some((item) => item.id === selectedId.value))
      selectedId.value = items[0]?.id ?? ''
  },
  { immediate: true },
)

const selectedCategory = computed(() =>
  props.content.items.find((category) => category.id === selectedId.value),
)

async function selectNext(currentId: string, direction: 1 | -1) {
  const items = props.content.items
  const index = items.findIndex((category) => category.id === currentId)
  const next = items[(index + direction + items.length) % items.length]
  if (!next) return
  selectedId.value = next.id
  await nextTick()
  document.getElementById(`tab-${next.id}`)?.focus()
}
</script>

<template>
  <section
    id="materials"
    class="section section--soft materials-section"
    aria-labelledby="materials-title"
  >
    <div class="container">
      <SectionHeading
        title-id="materials-title"
        :eyebrow="content.eyebrow"
        :title="content.title"
        :description="content.description"
      />

      <div
        class="materials-section__tabs"
        role="tablist"
        aria-label="Виды материалов"
      >
        <button
          v-for="category in content.items"
          :id="`tab-${category.id}`"
          :key="category.id"
          type="button"
          role="tab"
          :aria-selected="category.id === selectedId"
          aria-controls="materials-panel"
          :tabindex="category.id === selectedId ? 0 : -1"
          :class="{ 'is-active': category.id === selectedId }"
          @click="selectedId = category.id"
          @keydown.right.prevent="selectNext(category.id, 1)"
          @keydown.left.prevent="selectNext(category.id, -1)"
        >
          {{ category.name }}
        </button>
      </div>

      <div
        v-if="selectedCategory"
        id="materials-panel"
        :key="selectedCategory.id"
        role="tabpanel"
        :aria-labelledby="`tab-${selectedCategory.id}`"
        :tabindex="selectedCategory.linkUrl ? undefined : 0"
      >
        <component
          :is="selectedCategory.linkUrl ? NuxtLink : 'div'"
          v-bind="
            selectedCategory.linkUrl ? { to: selectedCategory.linkUrl } : {}
          "
          :aria-label="
            selectedCategory.linkUrl ? selectedCategory.name : undefined
          "
          class="materials-section__panel"
        >
          <div class="materials-section__image">
            <img
              v-if="selectedCategory.image"
              :src="selectedCategory.image"
              :alt="selectedCategory.imageAlt"
              width="880"
              height="1100"
              loading="lazy"
            />
          </div>
          <div class="materials-section__copy">
            <span class="eyebrow">{{ content.eyebrow }}</span>
            <h3>{{ selectedCategory.name }}</h3>
            <p>{{ selectedCategory.description }}</p>
            <span
              v-if="selectedCategory.note"
              class="materials-section__note"
              >{{ selectedCategory.note }}</span
            >
          </div>
        </component>
      </div>
    </div>
  </section>
</template>

<style scoped>
.materials-section {
  scroll-margin-top: 78px;
}
.materials-section__tabs {
  display: flex;
  justify-content: safe center;
  gap: clamp(20px, 4vw, 44px);
  overflow-x: auto;
  /* Reserve the shared focus outline without shifting the tabs or next panel. */
  margin: -12px -6px 46px;
  padding: 6px;
}
.materials-section__tabs button {
  flex: 0 0 auto;
  position: relative;
  border: 0;
  padding: 12px 2px;
  background: transparent;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.15em;
  text-transform: uppercase;
}
.materials-section__tabs button::after {
  position: absolute;
  right: 0;
  bottom: 2px;
  left: 0;
  height: 1px;
  background: var(--color-ink);
  content: '';
  transform: scaleX(0);
  transition: transform 0.3s var(--ease-out);
}
.materials-section__tabs button.is-active {
  color: var(--color-ink);
}
.materials-section__tabs button.is-active::after {
  transform: scaleX(1);
}
.materials-section__panel {
  display: grid;
  min-height: 520px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  background: var(--color-beige);
}
.materials-section__image {
  min-height: 520px;
  overflow: hidden;
  background: var(--color-beige);
}
.materials-section__image img {
  width: 100%;
  height: 100%;
  max-height: 620px;
  object-fit: cover;
}
.materials-section__copy {
  min-width: 0;
  overflow-wrap: anywhere;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  padding: clamp(40px, 7vw, 96px);
}
.materials-section__copy h3 {
  margin: 18px 0 24px;
  font-size: clamp(30px, 3.4vw, 46px);
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1.1;
}
.materials-section__copy p {
  max-width: 410px;
  margin: 0;
  color: var(--color-ink-soft);
  font-size: 15px;
  line-height: 1.85;
}
.materials-section__note {
  display: block;
  max-width: 360px;
  margin-top: 42px;
  border-top: 1px solid var(--color-line-strong);
  padding-top: 16px;
  color: var(--color-ink-soft);
  font-size: 12px;
  line-height: 1.6;
}
@media (max-width: 900px) {
  .materials-section__panel {
    min-height: 0;
  }
  .materials-section__image {
    min-height: 420px;
  }
  .materials-section__copy {
    padding: 40px;
  }
}
@media (max-width: 700px) {
  .materials-section__tabs {
    justify-content: flex-start;
    gap: 26px;
    overflow-x: auto;
    margin-bottom: 26px;
    padding-bottom: 14px;
  }
  .materials-section__panel {
    grid-template-columns: minmax(0, 1fr);
  }
  .materials-section__image {
    min-height: 0;
    height: min(70vw, 420px);
  }
  .materials-section__copy {
    padding: 36px 24px 46px;
  }
  .materials-section__note {
    margin-top: 30px;
  }
}
</style>
