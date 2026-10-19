<script setup lang="ts">
import { computed } from 'vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import type { CategoryOverview } from '../types/category.types'

const props = defineProps<{
  overview?: CategoryOverview
  categoryName: string
}>()
const emit = defineEmits<{ retry: [] }>()
const sections = computed(() => {
  const { groups = [], attributes = [] } = props.overview ?? {}
  const known = new Set(groups.map((group) => group.id))
  const result = groups.map((group) => ({
    id: String(group.id),
    name: group.name,
    attributes: attributes.filter(
      (attribute) => attribute.attribute_group_id === group.id,
    ),
  }))
  const ungrouped = attributes.filter(
    (attribute) => attribute.attribute_group_id === null,
  )
  if (ungrouped.length)
    result.push({ id: 'ungrouped', name: 'Без группы', attributes: ungrouped })
  const other = attributes.filter(
    (attribute) =>
      attribute.attribute_group_id !== null &&
      !known.has(attribute.attribute_group_id),
  )
  if (other.length)
    result.push({
      id: 'other',
      name: 'Другие характеристики',
      attributes: other,
    })
  return result
})
</script>
<template>
  <div :aria-busy="!overview || overview.loading">
    <UiLoadingState
      v-if="!overview || overview.loading"
      label="Загрузка характеристик…"
    />
    <div v-else-if="overview.error" class="flex flex-wrap items-center gap-3">
      <UiAlert>{{ overview.error }}</UiAlert>
      <UiButton
        variant="surface"
        size="sm"
        :aria-label="`Повторить загрузку характеристик категории ${categoryName}`"
        @click="emit('retry')"
        >Повторить</UiButton
      >
    </div>
    <div v-else-if="sections.length" class="category-attribute-groups">
      <section
        v-for="section in sections"
        :key="section.id"
        :aria-label="section.name"
        class="min-w-0"
      >
        <h3
          class="break-words rounded-md bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-500"
        >
          {{ section.name }}
        </h3>
        <ul v-if="section.attributes.length" class="divide-y divide-gray-100">
          <li
            v-for="attribute in section.attributes"
            :key="attribute.id"
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-3 text-sm text-gray-500"
          >
            <span class="min-w-0 break-words"
              >{{ attribute.name
              }}<template v-if="attribute.unit"
                >, {{ attribute.unit }}</template
              ></span
            >
            <span
              v-if="attribute.is_required || attribute.is_filterable"
              class="flex flex-wrap gap-1.5"
            >
              <UiBadge v-if="attribute.is_required" tone="primary"
                >Обязательная</UiBadge
              >
              <UiBadge v-if="attribute.is_filterable" tone="primary"
                >В фильтрах</UiBadge
              >
            </span>
          </li>
        </ul>
        <p v-else class="px-3 py-3 text-sm text-gray-500" role="status">
          Нет назначенных характеристик.
        </p>
      </section>
    </div>
    <p v-else class="text-sm text-gray-500" role="status">
      Группы и характеристики не назначены.
    </p>
  </div>
</template>
<style scoped>
.category-attribute-groups {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr));
  gap: var(--admin-spacing-6);
}
</style>
