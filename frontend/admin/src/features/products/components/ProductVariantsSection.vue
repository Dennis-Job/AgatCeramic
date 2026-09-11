<script setup lang="ts">
import BaseCheckbox from '../../../components/BaseCheckbox.vue'
import BaseInput from '../../../components/BaseInput.vue'
import BaseSelect from '../../../components/BaseSelect.vue'
import type { ProductGroup } from '../../../services/productGroups'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { saveGroup, copiedFromProduct, groupForm, selectedGroupId, groupOptions, selectGroup, groupErrors, groupSearch, saving, searchGroupProducts, editing, displayAttributeValue } = useProductEditorContext()
const attributes: any[] = useProductEditorContext().attributes
const groupProducts: ProductGroup['products'] = useProductEditorContext().groupProducts
</script>

<template>
<form id="product-group-form" @submit.prevent="saveGroup">
  <h3 class="font-bold text-gray-900">Варианты модели</h3>
  <p class="text-sm text-gray-500">Объедините самостоятельные товары и выберите различающиеся характеристики.</p>
  <p v-if="copiedFromProduct&&groupForm.product_ids.includes(copiedFromProduct.id)" class="mt-3 break-words rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-600 [overflow-wrap:anywhere]" role="status">Исходный товар «{{copiedFromProduct.name}} · {{copiedFromProduct.sku}}» уже выбран. <template v-if="selectedGroupId">Новый товар подготовлен для добавления в существующую группу.</template><template v-else>Заполните параметры новой группы и выберите различающуюся характеристику.</template></p>
  <BaseSelect :model-value="selectedGroupId" class="mt-5 max-w-xl" :options="groupOptions" accessible-name="Группа вариантов" searchable @update:model-value="selectGroup"/>
  <div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Название группы<BaseInput v-model="groupForm.name" class="mt-1.5" required/></label><label class="text-sm font-medium">Уникальный код<BaseInput v-model="groupForm.code" class="mt-1.5" required/></label></div>
  <fieldset class="mt-5 rounded-xl border border-gray-300 p-4"><legend class="px-1 text-sm font-semibold">Различающиеся характеристики</legend><div class="grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="a in attributes.filter(item=>!['text','multiselect'].includes(item.type))" :key="a.id" v-model="groupForm.axis_attribute_ids" :value="a.id">{{a.name}}</BaseCheckbox></div><p v-if="groupErrors.axis_attribute_ids" role="alert" class="mt-2 text-sm text-error-500">{{groupErrors.axis_attribute_ids[0]}}</p></fieldset>
  <fieldset class="mt-5 rounded-xl border border-gray-300 p-4">
    <legend class="px-1 text-sm font-semibold">Товары группы</legend>
    <div class="mb-3 flex flex-col gap-2 sm:flex-row"><BaseInput v-model="groupSearch" class="min-w-0 flex-1" searchable placeholder="Название или SKU" aria-label="Поиск товаров для группы"/><button type="button" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold disabled:opacity-60" :disabled="saving" @click="searchGroupProducts">{{saving?'Поиск…':'Найти'}}</button></div><p class="mb-3 text-xs text-gray-500">Первые 25 совпадений той же категории и бренда. Уточните поиск, если товара нет.</p>
    <div class="grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="p in groupProducts.filter(item=>item.category_id===editing?.category_id&&item.brand_id===editing?.brand_id)" :key="p.id" v-model="groupForm.product_ids" :value="p.id"><span class="flex min-w-0 items-center gap-2"><img v-if="p.primary_image" :src="p.primary_image.url" :alt="p.primary_image.alt||p.name" class="h-9 w-9 shrink-0 rounded object-cover"><span class="min-w-0 break-words [overflow-wrap:anywhere]">{{p.name}} · {{p.sku}}<span v-if="p.axis_values?.length" class="block text-xs text-gray-500">{{p.axis_values.map(value=>`${value.attribute?.name??'Характеристика'}: ${displayAttributeValue(value.attribute,value.value)}`).join(' · ')}}</span></span></span></BaseCheckbox></div>
    <p v-if="groupErrors.product_ids" role="alert" class="mt-2 text-sm text-error-500">{{groupErrors.product_ids[0]}}</p>
  </fieldset>
</form>
</template>
