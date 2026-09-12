<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { relationSearch, saving, searchRelations, selectableCandidates, addRelation, relationOptions, relationTypes, relationErrors, saveRelations } = useProductEditorContext()
const relations: any[] = useProductEditorContext().relations
</script>

<template>
  <section id="product-review-relations" class="rounded-xl border border-gray-300 p-5" aria-labelledby="product-relations-title">
    <div><h3 id="product-relations-title" class="font-bold text-gray-900">Сопутствующие товары</h3><p class="text-sm text-gray-500">Добавьте товары, которые стоит предложить покупателю вместе с этой позицией.</p></div>
    <div class="mt-4 flex flex-col gap-2 lg:flex-row"><UiInput v-model="relationSearch" class="min-w-0 flex-1" searchable placeholder="Название или SKU" aria-label="Поиск сопутствующих товаров"/><button type="button" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 disabled:opacity-60" :disabled="saving" @click="searchRelations">{{saving?'Поиск…':'Найти'}}</button><button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving||!selectableCandidates.length" @click="addRelation"><Plus :size="17"/>Добавить</button></div>
    <p v-if="!relations.length" class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-500">Сопутствующие товары не добавлены.</p><div v-for="(r,i) in relations" :key="i" class="mt-3 rounded-lg border border-gray-200 p-3"><div class="grid gap-2 sm:grid-cols-[1fr_160px_90px_auto]"><UiSelect v-model="r.related_product_id" :options="relationOptions(r.related_product_id)" :accessible-name="`Сопутствующий товар ${i+1}`" searchable/><UiSelect v-model="r.type" :options="relationTypes" :accessible-name="`Тип связи ${i+1}`"/><UiInput v-model="r.sort_order" type="number" min="0" :aria-label="`Порядок связи ${i+1}`"/><button type="button" :aria-label="`Удалить связь ${i+1}`" class="rounded-lg p-2 text-error-500 transition hover:bg-error-50 disabled:opacity-40" :disabled="saving" @click="relations.splice(i,1)"><Trash2 :size="17"/></button></div><p v-if="relationErrors[i]" role="alert" class="mt-2 text-xs text-error-500">{{relationErrors[i]}}</p></div>
    <div class="mt-4 flex justify-end"><button type="button" class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-600 disabled:opacity-60" :disabled="saving" @click="saveRelations">{{saving?'Сохранение…':'Сохранить рекомендации'}}</button></div>
  </section>
</template>
