<script setup lang="ts">
import { useId } from 'vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const groupHelpId = useId()
const groupHelp =
  'Если группа для товара уже создана, выберите её. Чтобы создать новую, выберите в списке «Новая группа» и заполните название и уникальный код.'
const {
  saveGroup,
  copiedFromProduct,
  groupForm,
  selectedGroupId,
  groupOptions,
  selectGroup,
  groupErrors,
  groupSearch,
  saving,
  searchGroupProducts,
  editing,
  displayAttributeValue,
  attributes,
  groupProducts,
} = useProductEditorContext()
</script>

<template>
  <form id="product-group-form" class="space-y-4" @submit.prevent="saveGroup">
    <UiAlert
      v-if="
        copiedFromProduct &&
        groupForm.product_ids.includes(copiedFromProduct.id)
      "
      class="break-words [overflow-wrap:anywhere]"
      tone="info"
      live="polite"
      >Исходный товар «{{ copiedFromProduct.name }} ·
      {{ copiedFromProduct.sku }}» уже выбран.
      <template v-if="selectedGroupId"
        >Новый товар подготовлен для добавления в существующую группу.</template
      ><template v-else
        >Заполните параметры новой группы и выберите различающуюся
        характеристику.</template
      ></UiAlert
    >
    <UiCard>
      <template #header>
        <h3 class="font-semibold text-gray-900">Группа вариантов</h3>
      </template>
      <div class="space-y-4">
        <div>
          <UiField label="Группа товаров">
            <UiSelect
              :model-value="selectedGroupId"
              class="mt-1.5"
              :options="groupOptions"
              accessible-name="Группа товаров"
              :description-id="groupHelpId"
              searchable
              teleport-menu
              @update:model-value="selectGroup"
            />
          </UiField>
          <p
            :id="groupHelpId"
            class="mt-1 block text-xs font-normal text-gray-500"
          >
            {{ groupHelp }}
          </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <UiField label="Название группы" required
            ><UiInput v-model="groupForm.name" class="mt-1.5" required
          /></UiField>
          <UiField label="Уникальный код" required
            ><UiInput v-model="groupForm.code" class="mt-1.5" required
          /></UiField>
        </div>
      </div>
    </UiCard>
    <UiCard>
      <template #header>
        <h3 class="font-semibold text-gray-900">
          Различающиеся характеристики
        </h3>
      </template>
      <fieldset>
        <legend class="sr-only">Различающиеся характеристики</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          <UiCheckbox
            v-for="a in attributes.filter(
              (item) => !['text', 'multiselect'].includes(item.type),
            )"
            :key="a.id"
            v-model="groupForm.axis_attribute_ids"
            :value="a.id"
            >{{ a.name }}</UiCheckbox
          >
        </div>
        <p
          v-if="groupErrors.axis_attribute_ids"
          role="alert"
          class="mt-2 text-sm text-error-700"
        >
          {{ groupErrors.axis_attribute_ids[0] }}
        </p>
      </fieldset>
    </UiCard>
    <UiCard>
      <template #header>
        <h3 class="font-semibold text-gray-900">Товары группы</h3>
      </template>
      <fieldset>
        <legend class="sr-only">Товары группы</legend>
        <div class="mb-3 flex flex-col gap-2 sm:flex-row">
          <UiInput
            v-model="groupSearch"
            class="min-w-0 flex-1"
            searchable
            placeholder="Название или SKU"
            aria-label="Поиск товаров для группы"
          /><UiButton
            type="button"
            variant="secondary"
            :loading="saving"
            :disabled="saving"
            @click="searchGroupProducts"
            >{{ saving ? 'Поиск…' : 'Найти' }}</UiButton
          >
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
          <UiCheckbox
            v-for="p in groupProducts.filter(
              (item) =>
                item.category_id === editing?.category_id &&
                item.brand_id === editing?.brand_id,
            )"
            :key="p.id"
            v-model="groupForm.product_ids"
            :value="p.id"
            ><span class="flex min-w-0 items-center gap-2"
              ><img
                v-if="p.primary_image"
                :src="p.primary_image.url"
                :alt="p.primary_image.alt || p.name"
                class="h-9 w-9 shrink-0 rounded object-cover"
              /><span class="min-w-0 break-words [overflow-wrap:anywhere]"
                >{{ p.name }} · {{ p.sku
                }}<span
                  v-if="p.axis_values?.length"
                  class="block text-xs text-gray-500"
                  >{{
                    p.axis_values
                      .map(
                        (value) =>
                          `${value.attribute?.name ?? 'Характеристика'}: ${displayAttributeValue(value.attribute, value.value)}`,
                      )
                      .join(' · ')
                  }}</span
                ></span
              ></span
            ></UiCheckbox
          >
        </div>
        <p
          v-if="groupErrors.product_ids"
          role="alert"
          class="mt-2 text-sm text-error-700"
        >
          {{ groupErrors.product_ids[0] }}
        </p>
      </fieldset>
    </UiCard>
  </form>
</template>
