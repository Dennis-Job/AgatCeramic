<script setup lang="ts">
import { nextTick, ref, useId } from 'vue'
import { Plus, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
import type { ProductRelationType } from '../types/product.types'

const searchContainer = ref<HTMLElement | null>(null)
const helpId = useId()
const {
  relations,
  relationSearch,
  relationSearchLoading,
  relationDetailsLoading,
  relationSearchError,
  relationSearchResult,
  relationSearchMessage,
  relationActionStatus,
  saving,
  searchRelations,
  addRelation,
  removeRelation,
  relationProduct,
  relationTypes,
  relationErrors,
  saveRelations,
} = useProductEditorContext()

async function add(type: ProductRelationType) {
  if (!addRelation(type)) return
  await nextTick()
  searchContainer.value?.querySelector('input')?.focus()
}
</script>

<template>
  <UiCard
    id="product-review-relations"
    aria-labelledby="product-relations-title"
  >
    <template #header>
      <h3 id="product-relations-title" class="font-bold text-gray-500">
        Сопутствующие и рекомендуемые товары
      </h3>
    </template>
    <div ref="searchContainer">
      <UiField label="Найти товар по SKU">
        <UiInput
          v-model="relationSearch"
          class="mt-1.5"
          searchable
          placeholder="Введите полный SKU"
          aria-label="SKU товара для добавления"
          :aria-describedby="helpId"
          :disabled="saving || relationDetailsLoading"
          @keydown.enter.prevent="searchRelations"
        />
      </UiField>
      <p :id="helpId" class="mt-1 text-xs text-gray-500">
        Введите SKU, проверьте найденный товар и выберите, куда его добавить.
      </p>
    </div>
    <UiLoadingState
      v-if="relationDetailsLoading"
      class="mt-3"
      label="Загрузка связанных товаров…"
    />
    <p
      v-else-if="relationSearchLoading"
      class="mt-3 text-sm text-gray-500"
      role="status"
    >
      Ищем товар…
    </p>
    <UiAlert v-else-if="relationSearchError" class="mt-3" tone="error">
      {{ relationSearchError }}
      <UiButton
        class="mt-2"
        type="button"
        variant="secondary"
        :disabled="saving"
        @click="searchRelations"
        >Повторить поиск</UiButton
      >
    </UiAlert>
    <div
      v-else-if="relationSearchResult"
      role="region"
      aria-label="Найденный товар"
      class="mt-3 rounded-xl border border-gray-100 bg-gray-25 p-3"
    >
      <p class="text-xs text-gray-500" role="status">Найден товар</p>
      <p
        class="mt-1 break-words text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
      >
        {{ relationSearchResult.name }}
      </p>
      <p class="mt-1 text-xs text-gray-500">
        SKU: {{ relationSearchResult.sku }}
      </p>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row">
        <UiButton type="button" :disabled="saving" @click="add('related')"
          ><Plus :size="17" aria-hidden="true" />В сопутствующие</UiButton
        >
        <UiButton type="button" :disabled="saving" @click="add('recommended')"
          ><Plus :size="17" aria-hidden="true" />В рекомендуемые</UiButton
        >
      </div>
    </div>
    <p
      v-else-if="relationSearchMessage"
      class="mt-3 text-sm text-gray-500"
      role="status"
    >
      {{ relationSearchMessage }}
    </p>
    <p
      v-else-if="relationActionStatus"
      class="mt-3 text-sm text-gray-500"
      role="status"
    >
      {{ relationActionStatus }}
    </p>
    <UiEmptyState
      v-if="!relations.length && !relationDetailsLoading"
      class="mt-4"
      label="Связанные товары пока не добавлены."
    />
    <ul v-else class="mt-4 space-y-3" aria-label="Добавленные товары">
      <li
        v-for="(relation, index) in relations"
        :key="relation.related_product_id"
        data-testid="product-relation-row"
        class="rounded-xl border border-gray-100 bg-gray-25 p-3"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div class="min-w-0 flex-1">
            <p
              class="break-words text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
            >
              {{
                relationProduct(relation.related_product_id)?.name ??
                'Товар недоступен'
              }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              SKU:
              {{ relationProduct(relation.related_product_id)?.sku ?? '—' }}
            </p>
          </div>
          <div
            class="grid min-w-0 gap-2 sm:grid-cols-[minmax(0,1fr)_90px_auto] sm:items-end lg:w-96 lg:shrink-0"
          >
            <UiField label="Тип связи">
              <UiSelect
                v-model="relation.type"
                class="mt-1.5"
                :options="relationTypes"
                :accessible-name="`Тип связи ${index + 1}`"
                teleport-menu
                :disabled="saving || relationDetailsLoading"
              />
            </UiField>
            <UiField label="Порядок">
              <UiInput
                v-model="relation.sort_order"
                class="mt-1.5"
                type="number"
                min="0"
                :aria-label="`Порядок связи ${index + 1}`"
                :disabled="saving || relationDetailsLoading"
              />
            </UiField>
            <UiButton
              type="button"
              variant="danger-ghost"
              :aria-label="`Удалить связь с товаром ${relationProduct(relation.related_product_id)?.sku ?? relation.related_product_id}`"
              :disabled="saving || relationDetailsLoading"
              @click="removeRelation(index)"
              ><Trash2 :size="17" aria-hidden="true"
            /></UiButton>
          </div>
        </div>
        <p
          v-if="relationErrors[index]"
          role="alert"
          class="mt-2 text-xs text-error-500"
        >
          {{ relationErrors[index] }}
        </p>
      </li>
    </ul>
    <div
      class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <p class="text-xs text-gray-500">
        Изменения применятся после сохранения связей.
      </p>
      <UiButton
        type="button"
        :loading="saving"
        :disabled="saving || relationDetailsLoading"
        @click="saveRelations"
        >{{ saving ? 'Сохранение…' : 'Сохранить связи' }}</UiButton
      >
    </div>
  </UiCard>
</template>
