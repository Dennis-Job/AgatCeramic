<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { countryName } from '../../../constants/countries'
import type { ProductCollection } from '../types/collection.types'
defineProps<{
  collections: ProductCollection[]
  loading: boolean
  filtered: boolean
  canManage: boolean
}>()
const emit = defineEmits<{
  edit: [ProductCollection]
  remove: [ProductCollection]
}>()
</script>
<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка коллекций…"
  />
  <ul
    v-else-if="collections.length"
    class="admin-container"
    aria-label="Коллекции брендов"
  >
    <li
      v-for="collection in collections"
      :key="collection.id"
      class="border-t border-gray-100 py-6"
    >
      <article
        class="min-w-0"
        :aria-labelledby="`collection-title-${collection.id}`"
      >
        <div class="collection-header">
          <UiImagePreview
            compact
            class="collection-image"
            :url="
              collection.image?.thumbnail_url || collection.image?.url || null
            "
            :alt="collection.image?.alt || collection.name"
          />
          <div class="min-w-0 text-sm text-gray-500 [overflow-wrap:anywhere]">
            <h2
              :id="`collection-title-${collection.id}`"
              class="collection-name font-semibold"
            >
              {{ collection.name }}
            </h2>
            <p class="mt-1">/{{ collection.slug }}</p>
          </div>
          <div
            class="collection-info min-w-0 text-sm text-gray-500 [overflow-wrap:anywhere]"
          >
            <div class="flex flex-wrap gap-2">
              <UiBadge :tone="collection.is_active ? 'success' : 'neutral'">{{
                collection.is_active ? 'Активна' : 'Неактивна'
              }}</UiBadge>
              <UiBadge
                :tone="
                  collection.description?.trim() && collection.image
                    ? 'neutral'
                    : 'warning'
                "
                >{{
                  collection.description?.trim() && collection.image
                    ? 'Блок заполнен'
                    : 'Блок не заполнен'
                }}</UiBadge
              >
            </div>
            <p class="mt-2">
              {{ collection.brand?.name || 'Бренд не найден' }} ·
              {{
                collection.brand?.country_code
                  ? countryName(collection.brand.country_code)
                  : 'Страна не указана'
              }}
            </p>
            <p class="mt-1">Товаров: {{ collection.products_count }}</p>
          </div>
          <div v-if="canManage" class="collection-actions flex gap-1">
            <UiButton
              variant="primary-ghost"
              size="sm"
              tooltip="Редактировать коллекцию"
              :aria-label="`Редактировать коллекцию ${collection.name}`"
              @click="emit('edit', collection)"
              ><Pencil :size="17"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              tooltip="Удалить коллекцию"
              :aria-label="`Удалить коллекцию ${collection.name}`"
              @click="emit('remove', collection)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </div>
        <p
          class="collection-description mt-4 whitespace-pre-wrap text-sm text-gray-500 [overflow-wrap:anywhere]"
        >
          {{ collection.description || 'Описание не добавлено.' }}
        </p>
      </article>
    </li>
  </ul>
  <UiEmptyState
    v-else
    class="admin-container"
    :label="
      filtered
        ? 'Коллекции не найдены. Попробуйте изменить фильтры.'
        : 'Коллекций пока нет.'
    "
  />
</template>

<style scoped>
.collection-header {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  column-gap: var(--admin-spacing-4);
  row-gap: var(--admin-spacing-3);
}
.collection-image {
  width: calc(var(--admin-spacing-4) * 4);
  height: calc(var(--admin-spacing-4) * 4);
}
.collection-name {
  font-size: 1.25rem;
  line-height: 1.5;
}
.collection-info,
.collection-actions {
  grid-column: 2;
}
@media (min-width: 640px) {
  .collection-header {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }
  .collection-actions {
    grid-column: 3;
    grid-row: 1;
  }
  .collection-description {
    margin-left: calc(var(--admin-spacing-4) * 5);
  }
}
@media (min-width: 1024px) {
  .collection-header {
    grid-template-columns: auto minmax(180px, 1fr) minmax(0, 0.65fr) auto;
  }
  .collection-info {
    grid-column: 3;
  }
  .collection-actions {
    grid-column: 4;
  }
}
</style>
