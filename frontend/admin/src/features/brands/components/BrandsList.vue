<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { countryName } from '../../../constants/countries'
import type { Brand } from '../types/brand.types'

defineProps<{
  brands: Brand[]
  loading: boolean
  canManage: boolean
  search?: string
}>()
const emit = defineEmits<{ edit: [brand: Brand]; remove: [brand: Brand] }>()
</script>

<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка брендов…"
  />
  <div v-else-if="brands.length" class="admin-container">
    <ul class="brand-list" aria-label="Бренды и подробная информация">
      <li v-for="brand in brands" :key="brand.id" class="brand-list-item">
        <article :aria-labelledby="`brand-title-${brand.id}`" class="min-w-0">
          <header class="brand-overview-header">
            <UiImagePreview
              compact
              class="brand-logo"
              :url="brand.logo?.thumbnail_url || brand.logo?.url || null"
              :alt="brand.logo?.alt || `Логотип ${brand.name}`"
            />
            <div class="min-w-0">
              <h2
                :id="`brand-title-${brand.id}`"
                class="brand-name font-semibold text-gray-500"
              >
                {{ brand.name }}
              </h2>
              <p class="mt-1 text-sm text-gray-500 [overflow-wrap:anywhere]">
                /{{ brand.slug }}
              </p>
            </div>
            <div class="brand-info min-w-0 text-sm text-gray-500">
              <UiBadge :tone="brand.is_active ? 'success' : 'neutral'">{{
                brand.is_active ? 'Активен' : 'Скрыт'
              }}</UiBadge>
              <p class="mt-2 [overflow-wrap:anywhere]">
                {{
                  brand.country_code
                    ? `Страна: ${countryName(brand.country_code)}`
                    : 'Страна не указана'
                }}
              </p>
            </div>
            <div v-if="canManage" class="brand-actions flex gap-1">
              <UiButton
                variant="primary-ghost"
                size="sm"
                tooltip="Редактировать бренд"
                :aria-label="`Редактировать бренд ${brand.name}`"
                @click="emit('edit', brand)"
                ><Pencil :size="17"
              /></UiButton>
              <UiButton
                variant="danger-ghost"
                size="sm"
                tooltip="Удалить бренд"
                :aria-label="`Удалить бренд ${brand.name}`"
                @click="emit('remove', brand)"
                ><Trash2 :size="17"
              /></UiButton>
            </div>
          </header>
          <div class="brand-details space-y-4 text-sm text-gray-500">
            <p class="whitespace-pre-wrap [overflow-wrap:anywhere]">
              {{ brand.description || 'Описание не добавлено.' }}
            </p>
            <div>
              <h3 class="font-semibold">
                Документы<template v-if="brand.documents.length">
                  ({{ brand.documents.length }})</template
                >
              </h3>
              <ul
                v-if="brand.documents.length"
                class="mt-2 flex flex-wrap gap-x-4 gap-y-2"
                aria-label="Документы бренда"
              >
                <li
                  v-for="document in brand.documents"
                  :key="document.id"
                  class="min-w-0 max-w-full"
                >
                  <a
                    :href="document.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="admin-focus text-primary-500 underline [overflow-wrap:anywhere]"
                    >{{ document.title }}</a
                  >
                </li>
              </ul>
              <p v-else class="mt-1">Документы не добавлены.</p>
            </div>
          </div>
        </article>
      </li>
    </ul>
  </div>
  <UiEmptyState
    v-else
    class="admin-container"
    :label="
      search?.trim()
        ? 'Бренды не найдены. Попробуйте другой запрос.'
        : 'Брендов пока нет.'
    "
  />
</template>

<style scoped>
.brand-list {
  list-style: none;
}
.brand-list-item {
  padding-block: var(--admin-spacing-6);
  border-top: var(--admin-border-width) solid var(--admin-color-gray-100);
}
.brand-overview-header {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  column-gap: var(--admin-spacing-4);
  row-gap: var(--admin-spacing-3);
}
.brand-logo {
  width: calc(var(--admin-spacing-4) * 4);
  height: calc(var(--admin-spacing-4) * 4);
}
.brand-name {
  font-size: 1.25rem;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.brand-info,
.brand-actions {
  grid-column: 2;
}
.brand-details {
  margin-top: var(--admin-spacing-4);
}
@media (min-width: 640px) {
  .brand-overview-header {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }
  .brand-actions {
    grid-column: 3;
    grid-row: 1;
  }
  .brand-details {
    margin-left: calc(var(--admin-spacing-4) * 5);
  }
}
@media (min-width: 1024px) {
  .brand-overview-header {
    grid-template-columns: auto minmax(180px, 1fr) minmax(0, 0.65fr) auto;
  }
  .brand-info {
    grid-column: 3;
  }
  .brand-actions {
    grid-column: 4;
  }
}
</style>
