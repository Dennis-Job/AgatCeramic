<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { relations, relationSearch, saving, searchRelations, selectableCandidates, addRelation, relationOptions, relationTypes, relationErrors, saveRelations } = useProductEditorContext()
</script>

<template>
  <section id="product-review-relations" class="rounded-xl border border-gray-300 p-5" aria-labelledby="product-relations-title">
    <div><h3 id="product-relations-title" class="font-bold text-gray-900">Сопутствующие товары</h3><p class="text-sm text-gray-500">Добавьте товары, которые стоит предложить покупателю вместе с этой позицией.</p></div>
    <div class="mt-4 flex flex-col gap-2 lg:flex-row"><UiInput v-model="relationSearch" class="min-w-0 flex-1" searchable placeholder="Название или SKU" aria-label="Поиск сопутствующих товаров" /><UiButton type="button" variant="secondary" :loading="saving" :disabled="saving" @click="searchRelations">{{ saving ? 'Поиск…' : 'Найти' }}</UiButton><UiButton type="button" variant="secondary" :disabled="saving || !selectableCandidates.length" @click="addRelation"><Plus :size="17" />Добавить</UiButton></div>
    <UiEmptyState v-if="!relations.length" class="mt-4 rounded-lg bg-gray-50" label="Сопутствующие товары не добавлены." />
    <div v-for="(relation, index) in relations" :key="index" class="mt-3 rounded-lg border border-gray-200 p-3"><div class="grid gap-2 sm:grid-cols-[1fr_160px_90px_auto]"><UiSelect v-model="relation.related_product_id" :options="relationOptions(relation.related_product_id)" :accessible-name="`Сопутствующий товар ${index + 1}`" searchable /><UiSelect v-model="relation.type" :options="relationTypes" :accessible-name="`Тип связи ${index + 1}`" /><UiInput v-model="relation.sort_order" type="number" min="0" :aria-label="`Порядок связи ${index + 1}`" /><UiButton type="button" variant="danger-ghost" size="sm" :aria-label="`Удалить связь ${index + 1}`" :disabled="saving" @click="relations.splice(index, 1)"><Trash2 :size="17" /></UiButton></div><p v-if="relationErrors[index]" role="alert" class="mt-2 text-xs text-error-500">{{ relationErrors[index] }}</p></div>
    <div class="mt-4 flex justify-end"><UiButton type="button" :loading="saving" :disabled="saving" @click="saveRelations">{{ saving ? 'Сохранение…' : 'Сохранить рекомендации' }}</UiButton></div>
  </section>
</template>
