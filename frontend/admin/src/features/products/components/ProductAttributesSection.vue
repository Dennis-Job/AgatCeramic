<script setup lang="ts">
import AttributeValueField from './AttributeValueField.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const {
  selectedGroupId,
  attributeSections,
  requiredIds,
  groupForm,
  attributeValues,
  attributeLabel,
  form,
  attributes,
  saveAttributes,
} = useProductEditorContext()
</script>

<template>
  <form id="product-attributes-form" @submit.prevent="saveAttributes">
    <h3 class="font-bold text-gray-900">Характеристики этой позиции</h3>
    <div class="mt-5 space-y-4">
      <UiCard
        v-for="section in attributeSections"
        :key="section.id"
        :aria-labelledby="`product-attribute-section-${section.id}`"
      >
        <template #header>
          <h4
            :id="`product-attribute-section-${section.id}`"
            class="font-semibold text-gray-800"
          >
            {{ section.name }}
          </h4>
        </template>
        <div class="grid gap-5 sm:grid-cols-2">
          <UiField
            v-for="attribute in section.attributes"
            :key="attribute.id"
            :label="`${attributeLabel(attribute)}${selectedGroupId ? ` · ${groupForm.axis_attribute_ids.includes(attribute.id) ? 'только для этой позиции' : 'общая для группы'}` : ''}`"
            :required="requiredIds.includes(attribute.id)"
          >
            <AttributeValueField
              v-model="attributeValues[attribute.id]"
              class="mt-1.5"
              :attribute="attribute"
              :accessible-name="
                selectedGroupId
                  ? `${attributeLabel(attribute)}, ${groupForm.axis_attribute_ids.includes(attribute.id) ? 'только для этой позиции' : 'общая для группы'}`
                  : attributeLabel(attribute)
              "
              :required="form.is_active && requiredIds.includes(attribute.id)"
              :clearable="!requiredIds.includes(attribute.id)"
            />
          </UiField>
        </div>
      </UiCard>
      <UiEmptyState
        v-if="!attributes.length"
        class="mt-4"
        label="Для этой категории характеристики пока не назначены."
      />
    </div>
  </form>
</template>
