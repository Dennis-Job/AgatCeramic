<script setup lang="ts">
import AttributeValueField from './AttributeValueField.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { selectedGroupId, attributeSections, requiredIds, groupForm, attributeValues, attributeLabel, form, attributes, saveAttributes } = useProductEditorContext()
</script>

<template>
<form id="product-attributes-form" @submit.prevent="saveAttributes">
  <h3 class="font-bold text-gray-900">Характеристики этой позиции</h3>
  <p class="mt-1 text-sm text-gray-500"><template v-if="selectedGroupId">Различающиеся характеристики изменяются только у этой позиции. Общие характеристики автоматически применяются ко всем товарам группы.</template><template v-else>Характеристики принадлежат только этой позиции. Черновик можно сохранить незаполненным.</template></p>
  <div class="mt-5 space-y-4">
    <section v-for="section in attributeSections" :key="section.id" class="overflow-hidden rounded-xl border border-gray-200" :aria-labelledby="`product-attribute-section-${section.id}`">
      <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 sm:px-5"><h4 :id="`product-attribute-section-${section.id}`" class="font-semibold text-gray-800">{{ section.name }}</h4></div>
      <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-5">
        <UiField v-for="attribute in section.attributes" :key="attribute.id" :label="`${attributeLabel(attribute)}${selectedGroupId ? ` · ${groupForm.axis_attribute_ids.includes(attribute.id) ? 'только для этой позиции' : 'общая для группы'}` : ''}`" :required="requiredIds.includes(attribute.id)">
          <AttributeValueField v-model="attributeValues[attribute.id]" class="mt-1.5" :attribute="attribute" :accessible-name="selectedGroupId ? `${attributeLabel(attribute)}, ${groupForm.axis_attribute_ids.includes(attribute.id) ? 'только для этой позиции' : 'общая для группы'}` : attributeLabel(attribute)" :required="form.is_active && requiredIds.includes(attribute.id)" :clearable="!requiredIds.includes(attribute.id)" />
        </UiField>
      </div>
    </section>
    <UiEmptyState v-if="!attributes.length" class="rounded-xl border border-dashed border-gray-200" label="Для этой категории характеристики пока не назначены." />
  </div>
</form>
</template>
